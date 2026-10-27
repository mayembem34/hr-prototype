<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('training_assessments',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('course_id')->constrained('training_courses')->restrictOnDelete();$t->unsignedTinyInteger('pass_percent')->default(70);$t->timestamp('published_at')->nullable();$t->timestamps();$t->unique('course_id');});
  Schema::create('training_questions',function(Blueprint $t){$t->id();$t->foreignId('assessment_id')->constrained('training_assessments')->restrictOnDelete();$t->text('prompt');$t->text('options');$t->unsignedTinyInteger('correct_option');$t->timestamps();});
  Schema::create('training_attempts',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('assessment_id')->constrained('training_assessments')->restrictOnDelete();$t->foreignId('assignment_id')->constrained('training_assignments')->restrictOnDelete();$t->unsignedInteger('correct_count');$t->unsignedInteger('question_count');$t->unsignedTinyInteger('score_percent');$t->boolean('passed');$t->timestamps();$t->index(['organization_id','assignment_id']);});
  foreach([['training.assessments.view','View training assessments','organization'],['training.assessments.manage','Create and publish training assessments','organization'],['training.results.view','View employee assessment results','organization'],['training.self.assessment.view','View own training assessment','session'],['training.self.assessment.submit','Submit own training assessment','session']] as [$key,$label,$scope])DB::table('permissions')->insert(['key'=>$key,'label'=>$label,'scope'=>$scope,'created_at'=>now(),'updated_at'=>now()]);
 }
 public function down(): void {DB::table('permissions')->whereIn('key',['training.assessments.view','training.assessments.manage','training.results.view','training.self.assessment.view','training.self.assessment.submit'])->delete();Schema::dropIfExists('training_attempts');Schema::dropIfExists('training_questions');Schema::dropIfExists('training_assessments');}
};
