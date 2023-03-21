<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

use App\Models\JobPosting;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

class JobPostingController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        // Get query parameters if exists
        $q = $request->input('q');
        $industry_id = $request->input('industry');
        $job_function_id = $request->input('job_function');

        // Build the query
        $query = JobPosting::query()->with(['location', 'jobFunction', 'industry', 'employer']);

        if (!empty($q)) {
            $query->where('title', 'like', '%' . $q . '%');
        }
        if (!empty($industry_id)) {
            $query->where('industry_id', $industry_id);
        }
        if (!empty($job_function_id)) {
            $query->where('job_function_id', $job_function_id);
        }

        $jobPostings = $query->paginate(10);

        $industries  = DB::table('industries')->pluck('id', 'name');
        $job_functions = DB::table('job_functions')->pluck('id', 'name');

        return view('job_postings.jobpostings', compact('jobPostings', 'industries', 'job_functions'))->with('q', $q)->with('job_function_id', $job_function_id)->with('industry_id', $industry_id);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', JobPosting::class);
        return view('job_postings.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', JobPosting::class);

        $validatedData = $request->validate([
            'title' => 'required|max:255',
            'description' => 'required',
            'requirements' => 'required',
            'type' => 'required|in:remote,on_site',
            'time' => 'required|in:full_time,part_time',
            'location' => 'required',
        ]);

        $location = Location::firstOrCreate([
            'name' => strtolower($validatedData['location'])
        ]);


        $jobPosting = new JobPosting;
        $jobPosting->title = $validatedData['title'];
        $jobPosting->description = $validatedData['description'];
        $jobPosting->requirements = $validatedData['requirements'];
        $jobPosting->type = $validatedData['type'];
        $jobPosting->time = $validatedData['time'];
        $jobPosting->salary = $request->salary ? (int)$request->salary : null;

        $jobPosting->location_id = $location->id;
        $jobPosting->user_id = $request->user()->id;

        $jobPosting->save();

        $status = 'success';
        $message = 'Job Posting created successfully!';
        return redirect()->route('admin')->with('flashes', [compact('status', 'message')]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $jobPosting = JobPosting::findOrFail($id);

        $relatedJobPostings = JobPosting::with(['location', 'jobFunction', 'industry', 'employer'])
            ->where([
                ['industry_id', '=', $jobPosting->industry->id],
                ['id', '!=', $jobPosting->id],
            ])
            ->orWhere([
                ['job_function_id', '=', $jobPosting->jobFunction->id],
                ['id', '!=', $jobPosting->id],
            ])
            ->limit(5)
            ->get();

        return view('job_postings.detail', [
            'jobPosting' => $jobPosting,
            'relatedJobPostings' => $relatedJobPostings
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $jobPosting = JobPosting::find($id);
        $this->authorize('update', $jobPosting);

        return view('job_postings.edit', [
            'jobPosting' => $jobPosting
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Get the jobPosting instance
        $jobPosting = JobPosting::find($id);
        // Authorize
        $this->authorize('update', $jobPosting);

        $validatedData = $request->validate([
            'title' => 'required',
            'description' => 'required',
            'requirements' => 'required',
            'type' => 'required|in:remote,on_site',
            'time' => 'required|in:full_time,part_time',
            'location' => 'required',
        ]);


        $location = Location::firstOrCreate([
            'name' => strtolower($validatedData['location'])
        ]);

        $jobPosting->title = $validatedData['title'];
        $jobPosting->description = $validatedData['description'];
        $jobPosting->requirements = $validatedData['requirements'];
        $jobPosting->type = $validatedData['type'];
        $jobPosting->time = $validatedData['time'];

        $jobPosting->salary = $request->salary ? (int)$request->salary : null;
        $jobPosting->location_id = $location->id;

        $jobPosting->save();

        $status = 'success';
        $message = 'Job Posting updated successfully!';
        return redirect()->route('jobpostings.admin')->with('flashes', [compact('status', 'message')]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $jobPosting = JobPosting::findOrFail($id);

        // authorize the user to delete the job posting
        $this->authorize('delete', $jobPosting);

        $jobPosting->delete();

        $status = 'success';
        $message = 'Job Posting has been deleted!';
        return redirect()->route('jobpostings.admin')->with('flashes', [compact('status', 'message')]);
    }

    /**
     * Admin panel for user of type 'employer'
     */
    public function admin(Request $request)
    {
        // Get the authenticated user
        $user = $request->user();

        // Get the job postings for the user
        $jobPostings = $user->employer->jobPostings()->with('applicants')->orderBy('created_at', 'desc')->paginate(10);

        // Render the admin view with the job postings
        return view('admin.index', compact('jobPostings'));
    }
}
