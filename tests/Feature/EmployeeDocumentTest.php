<?php
namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeDocumentTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); Storage::fake('local'); }
    private function admin(Organization $org): User { $u=User::factory()->create(); $org->users()->attach($u,['is_admin'=>true]); return $u; }
    private function member(Organization $org, array $keys): User {
        $u=User::factory()->create(); $org->users()->attach($u,['is_admin'=>false]); $role=new Role(['name'=>'Reader']); $role->organization_id=$org->id; $role->save();
        $role->permissions()->sync(Permission::whereIn('key',$keys)->pluck('id'));
        DB::table('organization_role_user')->insert(['organization_id'=>$org->id,'role_id'=>$role->id,'user_id'=>$u->id]); return $u;
    }
    private function employee(Organization $org): Employee { $e=new Employee(['employee_number'=>'E1','first_name'=>'Alice','last_name'=>'Example','status'=>'active']); $e->organization_id=$org->id; $e->save(); return $e; }
    private function upload(Organization $org, Employee $employee, User $admin): EmployeeDocument {
        $file=UploadedFile::fake()->createWithContent('contract.pdf', "%PDF-1.4\nexample\n%%EOF");
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/employees/'.$employee->id.'/documents',['title'=>'Contract','category'=>'Contract','file'=>$file])->assertRedirect();
        return EmployeeDocument::where('employee_id',$employee->id)->firstOrFail();
    }
    public function test_upload_preview_download_archive_and_restore_are_logged(): void {
        $org=Organization::create(['name'=>'A']); $admin=$this->admin($org); $employee=$this->employee($org); $doc=$this->upload($org,$employee,$admin);
        Storage::disk('local')->assertExists($doc->storage_path);
        $base='/organizations/'.$org->id.'/employees/'.$employee->id.'/documents/'.$doc->id;
        $this->actingAs($admin)->get($base.'/preview')->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $this->actingAs($admin)->get($base.'/download')->assertOk();
        $this->actingAs($admin)->post($base.'/archive')->assertRedirect();
        $this->actingAs($admin)->get($base.'/download')->assertNotFound();
        $this->actingAs($admin)->post($base.'/restore')->assertRedirect();
        $this->actingAs($admin)->get($base.'/download')->assertOk();
        $this->assertDatabaseHas('employee_document_actions',['document_id'=>$doc->id,'action'=>'uploaded']);
        $this->assertDatabaseHas('employee_document_actions',['document_id'=>$doc->id,'action'=>'archived']);
        $this->assertDatabaseHas('employee_document_actions',['document_id'=>$doc->id,'action'=>'restored']);
        $this->assertDatabaseHas('employee_document_actions',['document_id'=>$doc->id,'action'=>'download_requested']);
    }
    public function test_read_only_list_cannot_download_or_archive(): void {
        $org=Organization::create(['name'=>'A']); $admin=$this->admin($org); $employee=$this->employee($org); $doc=$this->upload($org,$employee,$admin); $reader=$this->member($org,['employees.view','documents.view']);
        $base='/organizations/'.$org->id.'/employees/'.$employee->id.'/documents';
        $this->actingAs($reader)->get($base)->assertOk()->assertSee('Contract')->assertDontSee('Download')->assertDontSee('Document action history');
        $this->actingAs($reader)->get($base.'/'.$doc->id.'/download')->assertForbidden();
        $this->actingAs($reader)->post($base.'/'.$doc->id.'/archive')->assertForbidden();
    }
    public function test_cross_organization_and_wrong_employee_are_rejected(): void {
        $a=Organization::create(['name'=>'A']); $b=Organization::create(['name'=>'B']); $adminA=$this->admin($a); $adminB=$this->admin($b); $empA=$this->employee($a); $empB=$this->employee($b); $doc=$this->upload($b,$empB,$adminB);
        $this->actingAs($adminA)->get('/organizations/'.$a->id.'/employees/'.$empA->id.'/documents/'.$doc->id.'/download')->assertNotFound();
        $this->actingAs($adminA)->get('/organizations/'.$b->id.'/employees/'.$empB->id.'/documents/'.$doc->id.'/download')->assertForbidden();
    }
    public function test_upload_rejects_unapproved_files_and_employee_permission_is_required(): void {
        $org=Organization::create(['name'=>'A']); $admin=$this->admin($org); $employee=$this->employee($org); $member=$this->member($org,['documents.upload']);
        $base='/organizations/'.$org->id.'/employees/'.$employee->id.'/documents';
        $this->actingAs($admin)->post($base,['title'=>'Bad','category'=>'Other','file'=>UploadedFile::fake()->createWithContent('test.txt','text')])->assertSessionHasErrors('file');
        $this->actingAs($member)->post($base,['title'=>'No','category'=>'Other','file'=>UploadedFile::fake()->createWithContent('ok.pdf',"%PDF-1.4\n%%EOF")])->assertForbidden();
        $this->assertDatabaseCount('employee_documents',0);
    }
}
