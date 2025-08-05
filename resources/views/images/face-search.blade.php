<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Search - Image Search Engine</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
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
            border-color: #ff6b6b;
            background-color: #fff5f5;
        }
        
        .search-area.dragover {
            border-color: #ff6b6b;
            background-color: #ffe8e8;
        }
        
        .search-icon {
            font-size: 3rem;
            color: #ff6b6b;
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
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
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
            box-shadow: 0 10px 20px rgba(255, 107, 107, 0.3);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .nav-links {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .nav-link {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .nav-link:hover {
            color: #ee5a24;
        }
        
        .preview {
            margin: 20px 0;
            max-width: 200px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .face-info {
            background: #fff5f5;
            border: 1px solid #ffcccc;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            color: #cc3333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">👤 Face Search</div>
        <div class="subtitle">Upload a photo with a face to find similar faces</div>
        
        <div class="face-info">
            <strong>💡 Tip:</strong> Make sure the face is clearly visible and well-lit for best results.
        </div>
        
        <form action="{{ route('images.face-search') }}" method="POST" enctype="multipart/form-data" id="searchForm">
            @csrf
            <div class="search-area" id="searchArea">
                <div class="search-icon">📷</div>
                <div class="search-text">Click to select or drag & drop a photo with a face</div>
                <div style="color: #999; font-size: 0.9rem;">Supports: JPG, PNG, GIF</div>
                <input type="file" name="image" class="file-input" id="fileInput" accept="image/*" required>
            </div>
            
            <img id="preview" class="preview" style="display: none;">
            
            <button type="submit" class="btn" id="submitBtn" disabled>
                Search Similar Faces
            </button>
        </form>
        
        <div class="nav-links">
            <a href="{{ route('images.upload.form') }}" class="nav-link">📤 Upload Images</a>
            <a href="{{ route('images.search.form') }}" class="nav-link">🔍 General Search</a>
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
                    searchArea.style.borderColor = '#ff6b6b';
                    searchArea.style.backgroundColor = '#fff5f5';
                };
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html> 