<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Upload - Image Search Engine</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        
        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        
        .upload-area {
            border: 3px dashed #ddd;
            border-radius: 15px;
            padding: 40px 20px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: #667eea;
            background-color: #f8f9ff;
        }
        
        .upload-area.dragover {
            border-color: #667eea;
            background-color: #f0f2ff;
        }
        
        .upload-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .upload-text {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        
        .file-input {
            display: none;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .nav-links {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        
        .nav-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .nav-link:hover {
            color: #764ba2;
        }
        
        .preview {
            margin: 20px 0;
            max-width: 200px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🖼️ Image Upload</div>
        <div class="subtitle">Upload images to build your search database</div>
        
        @if(session('success'))
            <div class="success-message" id="successMessage">
                {{ session('success') }}
            </div>
        @endif
        
        @if(session('error'))
            <div class="error-message" id="errorMessage">
                {{ session('error') }}
            </div>
        @endif
        
        <form action="{{ route('images.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
            @csrf
            <div class="upload-area" id="uploadArea">
                <div class="upload-icon">📁</div>
                <div class="upload-text">Click to select or drag & drop an image</div>
                <div style="color: #999; font-size: 0.9rem;">Supports: JPG, PNG, GIF</div>
                <input type="file" name="image" class="file-input" id="fileInput" accept="image/*" required>
            </div>
            
            <img id="preview" class="preview" style="display: none;">
            
            <button type="submit" class="btn" id="submitBtn" disabled>
                Upload Image
            </button>
        </form>
        
        <div class="nav-links">
            <a href="{{ route('images.search.form') }}" class="nav-link">🔍 Search Images</a>
        </div>
    </div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const preview = document.getElementById('preview');
        const submitBtn = document.getElementById('submitBtn');
        
        uploadArea.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });
        
        fileInput.addEventListener('change', handleFileSelect);
        
        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    submitBtn.disabled = false;
                    uploadArea.style.borderColor = '#667eea';
                    uploadArea.style.backgroundColor = '#f8f9ff';
                };
                reader.readAsDataURL(file);
            }
        }

        // Auto-hide success and error messages after 5 seconds
        setTimeout(function() {
            var successMsg = document.getElementById('successMessage');
            var errorMsg = document.getElementById('errorMessage');
            if (successMsg) { successMsg.style.display = 'none'; }
            if (errorMsg) { errorMsg.style.display = 'none'; }
        }, 5000);
    </script>
</body>
</html>