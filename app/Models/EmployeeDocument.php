<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EmployeeDocument extends Model {
    protected $fillable = ['title','category','original_name','mime_type','storage_path','size','uploaded_by'];
    protected function casts(): array { return ['archived_at'=>'datetime']; }
}
