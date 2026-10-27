<?php
namespace Tests\Feature;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Services\YouTubeEmbed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class TrainingVideoTest extends TestCase {
 use RefreshDatabase;
 public function test_video_urls_are_parsed_without_trusting_other_hosts(): void {
  $expected='https://www.youtube-nocookie.com/embed/abcdefghijk';
  foreach(['https://www.youtube.com/watch?v=abcdefghijk&t=30','https://youtu.be/abcdefghijk?si=xyz','https://m.youtube.com/shorts/abcdefghijk','https://www.youtube.com/embed/abcdefghijk'] as $url) $this->assertSame($expected,YouTubeEmbed::url($url));
  foreach(['https://youtube.com.evil.example/watch?v=abcdefghijk','https://youtube.com@evil.example/watch?v=abcdefghijk','javascript:alert(1)','https://youtu.be/bad','https://youtube.com/watch?v[]=abcdefghijk'] as $url) $this->assertNull(YouTubeEmbed::url($url));
 }
 public function test_video_is_shown_to_course_staff_and_assigned_employee_only(): void {
  $o=Organization::create(['name'=>'A']);$admin=User::factory()->create();$user=User::factory()->create();$o->users()->attach($admin,['is_admin'=>true]);$o->users()->attach($user);$e=new Employee(['employee_number'=>'E1','first_name'=>'A','last_name'=>'B','status'=>'active']);$e->organization_id=$o->id;$e->user_id=$user->id;$e->save();$base='/organizations/'.$o->id;
  $this->actingAs($admin)->post($base.'/training',['title'=>'Video','active'=>1,'delivery_url'=>'https://youtu.be/abcdefghijk'])->assertRedirect();$id=DB::table('training_courses')->value('id');$embed='https://www.youtube-nocookie.com/embed/abcdefghijk';$this->get($base.'/training')->assertOk()->assertSee($embed);
  $this->actingAs($user)->get($base.'/my-training')->assertOk()->assertDontSee($embed);$this->post($base.'/training/'.$id.'/enroll')->assertRedirect();$this->get($base.'/my-training')->assertDontSee($embed);$a=DB::table('training_assignments')->first();$this->actingAs($admin)->post($base.'/training/assignments/'.$a->id.'/decision',['decision'=>'approved'])->assertRedirect();$this->actingAs($user)->get($base.'/my-training')->assertOk()->assertSee($embed);
 }
}
