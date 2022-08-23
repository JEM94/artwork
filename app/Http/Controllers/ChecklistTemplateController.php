<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Models\Checklist;
use App\Models\ChecklistTemplate;
use App\Models\Department;
use App\Models\TaskTemplate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class ChecklistTemplateController extends Controller
{

    public function __construct()
    {
        $this->authorizeResource(ChecklistTemplate::class);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Inertia\Response|\Inertia\ResponseFactory
     */
    public function index()
    {
        return inertia('ChecklistTemplates/ChecklistTemplateManagement', [
            'checklist_templates' => ChecklistTemplate::all()->map(fn($checklist_template) => [
                'id' => $checklist_template->id,
                'name' => $checklist_template->name,
                'user' => $checklist_template->user,
                'task_templates' => $checklist_template->task_templates->map(fn($task_template) => [
                    'id' => $task_template->id,
                    'name' => $task_template->name,
                    'description' => $task_template->description,
                    'done' => $task_template->done,
                ]),
                'departments' => $checklist_template->departments->map(fn($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'svg_name' => $department->svg_name,
                ]),
                'updated_at' => $checklist_template->updated_at,
                'created_at' => Carbon::parse($checklist_template->created_at)->format('d.m.Y, H:i')
            ]),
        ]);
    }

    public function search(SearchRequest $request) {

        return ChecklistTemplate::search($request->input('query'))->get()->map(fn($checklist_template) => [
            'id' => $checklist_template->id,
            'name' => $checklist_template->name,
            'user' => $checklist_template->user,
            'task_templates' => $checklist_template->task_templates->map(fn($task_template) => [
                'id' => $task_template->id,
                'name' => $task_template->name,
                'description' => $task_template->description,
                'done' => $task_template->done,
            ]),
            'departments' => $checklist_template->departments->map(fn($department) => [
                'id' => $department->id,
                'name' => $department->name,
                'svg_name' => $department->svg_name,
            ]),
            'updated_at' => $checklist_template->updated_at,
            'created_at' => Carbon::parse($checklist_template->created_at)->format('d.m.Y, H:i')
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Inertia\Response|\Inertia\ResponseFactory
     */
    public function create()
    {
        return inertia('ChecklistTemplates/Create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        if($request->checklist_id) {
            $this->createFromChecklist($request);
        }
        else {
            $this->createFromScratch($request);
        }

        return Redirect::route('checklist_templates.management')->with('success', 'ChecklistTemplate created.');
    }

    protected function createFromChecklist(Request $request) {
        $checklist = Checklist::where('id', $request->checklist_id)->first();

        $checklist_template = ChecklistTemplate::create([
            'name' => $checklist->name,
            'user_id' => $request->user_id
        ]);

        foreach ($checklist->tasks as $task) {
            TaskTemplate::create([
                'name' => $task->name,
                'description' => $task->description,
                'checklist_template_id' => $checklist_template->id
            ]);
        }

        $checklist_template->departments()->sync(
            collect($checklist->departments)
                ->map(function ($department) {

                    $this->authorize('update', Department::find($department['id']));

                    return $department['id'];
                })
        );



    }

    protected function createFromScratch(Request $request) {
        $checklist_template = ChecklistTemplate::create([
            'name' => $request->name,
            'user_id' => $request->user_id
        ]);

        $checklist_template->departments()->sync(
            collect($request->departments)
                ->map(function ($department) {

                    $this->authorize('update', Department::find($department['id']));

                    return $department['id'];
                })
        );

        if($request->task_templates) {
            $checklist_template->task_templates()->createMany($request->task_templates);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ChecklistTemplate  $checklistTemplate
     * @return \Inertia\Response|\Inertia\ResponseFactory
     */
    public function show(ChecklistTemplate $checklistTemplate)
    {
        return inertia('ChecklistTemplates/Show', [
            'checklist_template' => [
                'id' => $checklistTemplate->id,
                'name' => $checklistTemplate->name,
                'task_templates' => $checklistTemplate->task_templates->map(fn($task_template) => [
                    'id' => $task_template->id,
                    'name' => $task_template->name,
                    'description' => $task_template->description,
                    'done' => $task_template->done,
                ]),
                'departments' => $checklistTemplate->departments->map(fn($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'svg_name' => $department->svg_name,
                ]),
                'updated_at' => $checklistTemplate->updated_at,
                'created_at' => $checklistTemplate->created_at
            ]
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ChecklistTemplate  $checklistTemplate
     * @return \Inertia\Response|\Inertia\ResponseFactory
     */
    public function edit(ChecklistTemplate $checklistTemplate)
    {
        return inertia('ChecklistTemplates/Edit', [
            'checklist_template' => [
                'id' => $checklistTemplate->id,
                'name' => $checklistTemplate->name,
                'user' => $checklistTemplate->user,
                'tasks' => $checklistTemplate->task_templates->map(fn($task_template) => [
                    'id' => $task_template->id,
                    'name' => $task_template->name,
                    'description' => $task_template->description,
                    'done' => $task_template->done,
                ]),
                'departments' => $checklistTemplate->departments->map(fn($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'svg_name' => $department->svg_name,
                ])
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ChecklistTemplate  $checklistTemplate
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, ChecklistTemplate $checklistTemplate)
    {
        $checklistTemplate->update($request->only('name'));

        $checklistTemplate->departments()->sync(
            collect($request->departments)
                ->map(function ($department) {

                    $this->authorize('update', Department::find($department['id']));

                    return $department['id'];
                })
        );

        $checklistTemplate->task_templates()->delete();

        foreach($request->task_templates as $task_template) {
            if(!is_array($task_template)) {
                $checklistTemplate->task_templates()->save($task_template);
            }
            else {
                $checklistTemplate->task_templates()->create($task_template);
            }
        }

        return Redirect::route('checklist_templates.management', $checklistTemplate -> id)->with('success', 'ChecklistTemplate updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ChecklistTemplate  $checklistTemplate
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(ChecklistTemplate $checklistTemplate)
    {
        $checklistTemplate->delete();
        return Redirect::back()->with('success', 'ChecklistTemplate deleted');
    }
}
