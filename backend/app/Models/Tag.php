<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tag extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'name',
    ];

    /**
     * Get the task that owns the tag.
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
