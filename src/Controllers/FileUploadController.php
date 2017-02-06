<?php

namespace TheShop\Projects\Controllers;

use App\Exceptions\FileUploadException;
use App\GenericModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class FileUploadController extends Controller
{
    public function uploadFile(Request $request)
    {
        $userId = Auth::user()->id;

        if ($request->has('projectId')) {
            $projectId = $request->get('projectId');
        } else {
            $projectId = null;
        }

        $files = $request->file();

        $response = [];
        foreach ($files as $file) {
            if ($file->getError()) {
                throw new FileUploadException('File could not be uploaded.');
            }

            GenericModel::setCollection('uploads');
            $upload = GenericModel::create();

            $fileName = $userId . '-' . str_random(20) . '.' . $file->getClientOriginalExtension();

            $s3 = Storage::disk('s3');
            $filePath = $fileName;
            $s3->put($filePath, file_get_contents($file), 'public');

            $fileUrl = Storage::cloud()->url($fileName);

            $upload->projectId = $projectId;
            $upload->name = $file->getClientOriginalName();
            $upload->fileUrl = $fileUrl;
            $upload->save();

            $response[] = $upload;
        }

        return $this->jsonSuccess($response);
    }

    /**
     * Lists all uploaded files with set projectId
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectUploads(Request $request)
    {
        GenericModel::setCollection('projects');
        $project = GenericModel::find($request->route('id'));
        if (!$project) {
            return $this->jsonError(['Project with given ID not found'], 404);
        }

        GenericModel::setCollection('uploads');
        $uploads = GenericModel::where('projectId', '=', $request->route('id'))->get();

        return $this->jsonSuccess($uploads);
    }
}
