<?php

namespace Modules\RepoManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\RepoManagement\Entities\Repository;
use Illuminate\Support\Str;

class RepoManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $repos = Repository::all();
        return response()->json($repos);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('repomanagement::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $repoName = $request->name;
        $userId = (string) Str::uuid(); // temp (later from auth)
        $repoUuid = (string) Str::uuid();

        $basePath = "D:\\git-repos";

        // Clean repo folder name
        $folderName = "{$repoUuid}_{$repoName}.git";

        $repoPath = "{$basePath}\\{$folderName}";

        exec("git init --bare \"$repoPath\"", $output, $status);

        if ($status !== 0) {
            return response()->json([
                'error' => 'Git repo creation failed',
                'output' => $output
            ], 500);
        }

        $repo = Repository::create([
            'uuid' => $repoUuid,
            'name' => $repoName,
            'user_id' => $userId,
            'path' => $repoPath
        ]);

        return response()->json($repo);
    }

    public function files($id)
    {
        $repo = \Modules\RepoManagement\Entities\Repository::findOrFail($id);

        $path = $repo->path;

        // Git command to list files
        $command = "git --git-dir=\"$path\" ls-tree --name-only HEAD";

        exec($command, $output, $status);

        if ($status !== 0) {
            return response()->json([
                'error' => 'Failed to read repo',
                'output' => $output
            ], 500);
        }

        return response()->json([
            'files' => $output
        ]);
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('repomanagement::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('repomanagement::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}
