<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class EmployeeDocumentController extends Controller {
    private function authorizeDocument(Request $request, Organization $organization, Employee $employee, string $permission, ?EmployeeDocument $document = null): void {
        abort_unless($request->user()->hasPermission('employees.view', $organization) && $request->user()->hasPermission($permission, $organization), 403);
        abort_unless((int)$employee->organization_id === (int)$organization->id, 404);
        $access = app(\App\Services\EmployeeAccess::class);
        abort_unless($access->allows($request->user(), $organization, 'employees.view', $employee) && $access->allows($request->user(), $organization, $permission, $employee), 403);
        if ($document) abort_unless((int)$document->organization_id === (int)$organization->id && (int)$document->employee_id === (int)$employee->id, 404);
    }
    private function logAction(EmployeeDocument $document, Request $request, string $action): void {
        DB::table('employee_document_actions')->insert(['organization_id'=>$document->organization_id,'document_id'=>$document->id,'actor_id'=>$request->user()->id,'action'=>$action,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function index(Request $request, Organization $organization, Employee $employee) {
        $this->authorizeDocument($request, $organization, $employee, 'documents.view');
        $documents = EmployeeDocument::where('organization_id',$organization->id)->where('employee_id',$employee->id)->orderByDesc('id')->get();
        $actions = app(\App\Services\EmployeeAccess::class)->allows($request->user(), $organization, 'documents.audit.view', $employee)
            ? DB::table('employee_document_actions as actions')->join('employee_documents as documents','documents.id','=','actions.document_id')->leftJoin('users','users.id','=','actions.actor_id')
                ->where('actions.organization_id',$organization->id)->where('documents.organization_id',$organization->id)->where('documents.employee_id',$employee->id)
                ->select('actions.action','actions.created_at','documents.title','users.name as actor_name')->orderByDesc('actions.id')->limit(100)->get() : collect();
        return view('documents.index', compact('organization','employee','documents','actions'));
    }
    public function store(Request $request, Organization $organization, Employee $employee) {
        $this->authorizeDocument($request, $organization, $employee, 'documents.upload');
        $data = $request->validate(['title'=>'required|string|max:255', 'category'=>'required|string|max:100', 'file'=>'required|file|mimes:pdf,jpg,jpeg,png,docx|max:10240']);
        $file = $data['file']; $path = $file->store('employee-documents/'.$organization->id, 'local');
        if (! $path) abort(500, 'The document could not be stored.');
        $original = Str::limit(preg_replace('/[\x00-\x1f\x7f\/\\\\]+/u', '_', $file->getClientOriginalName()) ?: 'document', 200, '');
        try {
            DB::transaction(function () use ($organization,$employee,$request,$data,$path,$original,$file) {
                $document = new EmployeeDocument(['title'=>$data['title'],'category'=>$data['category'],'original_name'=>$original,'mime_type'=>$file->getMimeType() ?: 'application/octet-stream','storage_path'=>$path,'size'=>$file->getSize(),'uploaded_by'=>$request->user()->id]);
                $document->organization_id=$organization->id; $document->employee_id=$employee->id; $document->save();
                $this->logAction($document,$request,'uploaded');
            });
        } catch (Throwable $e) { Storage::disk('local')->delete($path); throw $e; }
        return back()->with('status','Document uploaded.');
    }
    public function preview(Request $request, Organization $organization, Employee $employee, EmployeeDocument $document) {
        $this->authorizeDocument($request,$organization,$employee,'documents.preview',$document);
        abort_if($document->archived_at,404);
        abort_unless(in_array($document->mime_type,['application/pdf','image/jpeg','image/png'],true),415);
        abort_unless(Storage::disk('local')->exists($document->storage_path),404);
        $this->logAction($document,$request,'preview_requested');
        return Storage::disk('local')->response($document->storage_path, $document->original_name, ['Content-Type'=>$document->mime_type,'X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"sandbox; default-src 'none'; style-src 'unsafe-inline'",'Cache-Control'=>'private, no-store'], 'inline');
    }
    public function download(Request $request, Organization $organization, Employee $employee, EmployeeDocument $document) {
        $this->authorizeDocument($request,$organization,$employee,'documents.download',$document);
        abort_if($document->archived_at,404); abort_unless(Storage::disk('local')->exists($document->storage_path),404);
        $this->logAction($document,$request,'download_requested');
        return Storage::disk('local')->download($document->storage_path, $document->original_name, ['Content-Type'=>'application/octet-stream','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
    }
    public function archive(Request $request, Organization $organization, Employee $employee, EmployeeDocument $document) {
        $this->authorizeDocument($request,$organization,$employee,'documents.archive',$document);
        DB::transaction(function () use ($document,$request) { $locked=EmployeeDocument::whereKey($document->id)->lockForUpdate()->firstOrFail(); if (! $locked->archived_at) { $locked->archived_at=now(); $locked->save(); $this->logAction($locked,$request,'archived'); } });
        return back()->with('status','Document archived.');
    }
    public function restore(Request $request, Organization $organization, Employee $employee, EmployeeDocument $document) {
        $this->authorizeDocument($request,$organization,$employee,'documents.restore',$document);
        DB::transaction(function () use ($document,$request) { $locked=EmployeeDocument::whereKey($document->id)->lockForUpdate()->firstOrFail(); if ($locked->archived_at) { $locked->archived_at=null; $locked->save(); $this->logAction($locked,$request,'restored'); } });
        return back()->with('status','Document restored.');
    }
}
