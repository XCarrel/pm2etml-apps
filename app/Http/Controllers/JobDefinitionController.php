<?php

namespace App\Http\Controllers;

use App\Constants\RoleName;
use App\Exceptions\DataIntegrityException;
use App\Http\Requests\StoreUpdateJobDefinitionRequest;
use App\Http\Requests\UpdateJobDefinitionRequest;
use App\Models\Attachment;
use App\Models\JobDefinition;
use App\Models\JobDefinitionMainImageAttachment;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;


class JobDefinitionController extends Controller
{

    public function __construct()
    {
        //map rbac authorization from policyClass
        $this->authorizeResource(JobDefinition::class, 'jobDefinition');
    }

    public function marketPlace()
    {
        $definitions = JobDefinition::query()
            ->where(fn($q)=>$q->published())
            ->where(fn($q)=>$q->available())
            ->whereNotIn('id',auth()->user()->contractsAsAWorker()->select('job_definition_id'))
            ->orderBy('required_xp_years')
            ->orderByDesc('one_shot')
            ->orderBy('priority')
            ->with('providers')
            ->with('image')
            ->with('attachments')
            ->with('skills.skillGroup')
            ->get();
        return view('marketplace')->with(compact('definitions'));
    }

    /**
     * JSON content
     *
     */
    public function index()
    {
        $definitions = JobDefinition::all();
        return $definitions;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        //ready for form reuse as edit...
        $job = new JobDefinition();

        return $this->createEdit($request,$job);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreUpdateJobDefinitionRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreUpdateJobDefinitionRequest $request)
    {
        return $this->storeUpdate($request);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\JobDefinition  $jobDefinition
     * @return \Illuminate\Http\Response
     */
    public function show(JobDefinition $jobDefinition)
    {
        return view('jobDefinition-view')->with(compact('jobDefinition'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\JobDefinition  $jobDefinition
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, JobDefinition $jobDefinition)
    {
        return $this->createEdit($request, $jobDefinition);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateJobDefinitionRequest  $request
     * @param  \App\Models\JobDefinition  $jobDefinition
     * @return \Illuminate\Http\Response
     */
    public function update(StoreUpdateJobDefinitionRequest $request, JobDefinition $jobDefinition)
    {
        return $this->storeUpdate($request,$jobDefinition);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\JobDefinition  $jobDefinition
     * @return \Illuminate\Http\Response
     */
    public function destroy(JobDefinition $jobDefinition)
    {
        //Mark attachments as deleted (do not use mass delete to keep EVENT processing)
        Attachment::where('attachable_id','=',$jobDefinition->id)
            ->each(fn($a)=>$a->delete());

        $jobDefinition->delete();
        return redirect(route('marketplace'))
            ->with('success', __('Job ":job" deleted', ['job' => $jobDefinition->title]));
    }

    protected function createEdit(Request $request,JobDefinition $jobDefinition)
    {
        //First arrival on form, we store the url the user comes from (to redirect after success)
        if(!Session::hasOldInput())
        {
            Session::put('start-url',$request->header('referer'));
        }

        $providers = User::role(RoleName::TEACHER)
            ->orderBy('firstname')
            ->orderBy('lastname')
            //Skip current user as it will be added on top
            ->where('id','!=',auth()->user()->id)
            ->get(['id','firstname','lastname']);

        list($pendingAndOrCurrentAttachments, $pendingOrCurrentImage) =
            $this->extractAttachmentState($jobDefinition);

        $availableSkills = Skill::query()
            ->whereNotIn(tbl(Skill::class).'.id',$jobDefinition->skills->pluck('id'))
            ->with('skillGroup')
            ->get();

        //Force eager load on object given by Laravel on route ...
        $jobDefinition->load('skills.skillGroup');

        return view('jobDefinition-create-update')
            ->with(compact(
                'providers',
                'pendingAndOrCurrentAttachments',
                'pendingOrCurrentImage','availableSkills'))
            ->with('job',$jobDefinition);
    }

    protected function storeUpdate(StoreUpdateJobDefinitionRequest $request,
                                   JobDefinition $jobDefinition=null)
    {
        $editMode = $jobDefinition!=null;

        //Group job, attachment,providers in same unit
        DB::transaction(function () use ($request, &$jobDefinition) {
            //Save to give an ID and then sync referenced tables
            if($jobDefinition==null)
            {
                //Use mass assignment ;-)
                $jobDefinition = JobDefinition::create($request->all());
            }
            else
            {
                $jobDefinition->update($request->all());
            }

            //First delete any removed attachments (including image)
            $attachmentIdsToDelete = json_decode($request->input('any_attachment_to_delete'));
            foreach (Attachment::findMany($attachmentIdsToDelete) as $attachment)
            {
                $attachment->delete();
            }

            //Image
            $image = $request->input('image');
            if($jobDefinition->image==null || $jobDefinition->image->id != $image)
            {
                $image = JobDefinitionMainImageAttachment::findOrFail($image);
                if($image->attachable_id!=null)
                {
                    throw new DataIntegrityException('Image already linked to another job');
                }
                $image->attachJobDefinition($jobDefinition);
            }

            //PROVIDERS
            //Handle relations (id must have been attributed)
            $providers = User::role(RoleName::TEACHER)
                ->whereIn('id', $request->input('providers'))
                ->pluck('id');
            $jobDefinition->providers()->sync($providers);

            //Attachments (already uploaded, we just bind them)
            $attachmentIds = json_decode($request->input('other_attachments'));
            foreach (Attachment::findMany(collect($attachmentIds)->values()) as $attachment)
            {
                $attachment->attachJobDefinition($jobDefinition);
            }

            //Skills
            $submittedSkills = collect(json_decode($request->input('skills')));
            $skillIds=$submittedSkills
                ->transform(fn($el)=>Skill::firstOrCreateFromString($el))
                ->pluck('id');
            $jobDefinition->skills()->sync($skillIds);

        });

        //Yeah, we made it ;-)
        $targetUrl = Session::get('start-url')??route('marketplace');
        return redirect()->to($targetUrl)
            ->with('success', __('Job ":job" '.($editMode?'updated':'created'), ['job' => $jobDefinition->title]));

    }

    /**
     * @param JobDefinition $jobDefinition
     * @return array
     */
    protected function extractAttachmentState(JobDefinition $jobDefinition): array
    {
        $old = old('other_attachments');
        if ($old == null) {
            $pendingAttachments = $jobDefinition->attachments->pluck('id');
        } else {
            $pendingAttachments = collect(json_decode($old))->values();
        }
        $pendingAndOrCurrentAttachments =
            \App\Models\JobDefinitionDocAttachment::findMany($pendingAttachments);

        $pendingOrCurrentImage =
            \App\Models\JobDefinitionMainImageAttachment::find(old('image',
                $jobDefinition->image?->id));

        return array($pendingAndOrCurrentAttachments, $pendingOrCurrentImage);
    }
}
