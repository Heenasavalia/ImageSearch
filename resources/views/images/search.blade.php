<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Search - Find Similar Human Faces</title>
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
        
        .search-area {
            border: 3px dashed #ddd;
            border-radius: 15px;
            padding: 40px 20px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .search-area:hover {
            border-color: #667eea;
            background-color: #f8f9ff;
        }
        
        .search-area.dragover {
            border-color: #667eea;
            background-color: #f0f2ff;
        }
        
        .search-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .search-text {
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
        
        .preview {
            margin: 20px 0;
            max-width: 200px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .info-box {
            background: #f8f9ff;
            border: 1px solid #e3e6ff;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
        }
        
        .info-box h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        
        .info-box ul {
            color: #666;
            padding-left: 20px;
        }
        
        .info-box li {
            margin-bottom: 5px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">👤 Face Search</div>
        <div class="subtitle">Upload a human face to find similar faces in your database</div>
        
        <div class="info-box">
            <h3>📋 How it works:</h3>
            <ul>
                <li>Upload an image containing a human face</li>
                <li>Our AI will detect and extract face features</li>
                <li>Find similar faces from your uploaded images</li>
                <li>Only human faces are matched (no animals, objects, etc.)</li>
            </ul>
        </div>
        
        <form action="{{ route('images.search') }}" method="POST" enctype="multipart/form-data" id="searchForm">
            @csrf
            <div class="search-area" id="searchArea">
                <div class="search-icon">📁</div>
                <div class="search-text">Click to select or drag & drop a face image</div>
                <div style="color: #999; font-size: 0.9rem;">Supports: JPG, PNG, GIF (max 10MB)</div>
                <input type="file" name="image" class="file-input" id="fileInput" accept="image/*" required>
            </div>
            
            <img id="preview" class="preview" style="display: none;">
            
            <button type="submit" class="btn" id="submitBtn" disabled>
                🔍 Search Similar Faces
            </button>
        </form>
        
        <div class="nav-links">
            <a href="{{ route('images.upload.form') }}" class="nav-link">📤 Upload Face Images</a>
        </div>
    </div>

    <script>
        const searchArea = document.getElementById('searchArea');
        const fileInput = document.getElementById('fileInput');
        const preview = document.getElementById('preview');
        const submitBtn = document.getElementById('submitBtn');
        
        searchArea.addEventListener('click', () => fileInput.click());
        
        searchArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            searchArea.classList.add('dragover');
        });
        
        searchArea.addEventListener('dragleave', () => {
            searchArea.classList.remove('dragover');
        });
        
        searchArea.addEventListener('drop', (e) => {
            e.preventDefault();
            searchArea.classList.remove('dragover');
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
                    searchArea.style.borderColor = '#667eea';
                    searchArea.style.backgroundColor = '#f8f9ff';
                };
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>