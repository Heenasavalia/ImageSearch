<?php

/**
 * Setup script for Face Search System
 * 
 * This script helps you set up the face search system by:
 * 1. Creating necessary directories
 * 2. Running database migrations
 * 3. Creating storage links
 * 4. Checking Python dependencies
 */

echo "🔧 Face Search System Setup\n";
echo "==========================\n\n";

// Check if we're in a Laravel project
if (!file_exists('artisan')) {
    echo "❌ Error: This doesn't appear to be a Laravel project.\n";
    echo "Please run this script from the root of your Laravel project.\n";
    exit(1);
}

// 1. Create necessary directories
echo "📁 Creating directories...\n";
$directories = [
    'storage/app/public/images',
    'storage/app/public/searches',
    'storage/logs'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Created: {$dir}\n";
        } else {
            echo "❌ Failed to create: {$dir}\n";
        }
    } else {
        echo "⏭️  Already exists: {$dir}\n";
    }
}

echo "\n";

// 2. Check if .env exists
echo "🔧 Checking environment...\n";
if (!file_exists('.env')) {
    if (file_exists('.env.example')) {
        copy('.env.example', '.env');
        echo "✅ Created .env from .env.example\n";
        echo "⚠️  Please configure your database settings in .env\n";
    } else {
        echo "❌ No .env.example found. Please create .env manually.\n";
    }
} else {
    echo "✅ .env file exists\n";
}

echo "\n";

// 3. Check Python dependencies
echo "🐍 Checking Python dependencies...\n";
$pythonDeps = [
    'opencv-python' => 'cv2',
    'face-recognition' => 'face_recognition',
    'numpy' => 'numpy',
    'pillow' => 'PIL'
];

$missingDeps = [];
foreach ($pythonDeps as $package => $import) {
    $output = shell_exec("python -c \"import {$import}; print('OK')\" 2>&1");
    if (strpos($output, 'OK') !== false) {
        echo "✅ {$package}\n";
    } else {
        echo "❌ {$package} - Missing\n";
        $missingDeps[] = $package;
    }
}

if (!empty($missingDeps)) {
    echo "\n⚠️  Missing Python packages. Install them with:\n";
    echo "pip install " . implode(' ', $missingDeps) . "\n";
}

echo "\n";

// 4. Check if face_detection.py exists
echo "🔍 Checking face detection script...\n";
if (file_exists('face_detection.py')) {
    echo "✅ face_detection.py found\n";
} else {
    echo "❌ face_detection.py not found\n";
    echo "Please ensure the face detection script is in the project root.\n";
}

echo "\n";

// 5. Instructions
echo "📋 Next Steps:\n";
echo "==============\n";
echo "1. Configure your database in .env file\n";
echo "2. Run: php artisan migrate\n";
echo "3. Run: php artisan storage:link\n";
echo "4. Add face images to storage/app/public/images/\n";
echo "5. Run: php add_images.php\n";
echo "6. Start server: php artisan serve\n";
echo "7. Visit: http://localhost:8000\n";

echo "\n🎉 Setup complete! Follow the steps above to get started.\n"; 