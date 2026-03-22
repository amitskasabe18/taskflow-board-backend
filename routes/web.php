<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test-git', function () {
    return exec('git --version');
});

Route::get('/create-repo', function () {

    $repoName = "test-from-laravel";
    $userId = 1;

    $basePath = "D:\\git-repos";
    $repoPath = "{$basePath}\\{$userId}_{$repoName}.git";

    // Run git command
    exec("git init --bare \"$repoPath\"", $output, $resultCode);

    return [
        'path' => $repoPath,
        'output' => $output,
        'status' => $resultCode
    ];
});