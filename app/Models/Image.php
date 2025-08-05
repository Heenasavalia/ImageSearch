<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = [
        'path', 
        'has_faces', 
        'face_count', 
        'face_features', 
        'face_rectangles'
    ];
    
    protected $casts = [
        'has_faces' => 'boolean',
        'face_count' => 'integer',
        'face_features' => 'array',
        'face_rectangles' => 'array',
    ];
}
