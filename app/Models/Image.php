<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = [
        'path', 
        'feature_vector', 
        'has_faces', 
        'face_count', 
        'face_features', 
        'face_rectangles'
    ];
    
    protected $casts = [
        'feature_vector' => 'string',
        'has_faces' => 'boolean',
        'face_count' => 'integer',
        'face_features' => 'array',
        'face_rectangles' => 'array',
    ];
}
