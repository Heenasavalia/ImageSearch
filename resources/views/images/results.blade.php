<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - Image Search Engine</title>
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
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #666;
            font-size: 1.1rem;
        }
        
        .results-count {
            background: #e3f2fd;
            color: #1976d2;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .no-results-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .result-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        
        .result-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .similarity-score {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .image-info {
            color: #666;
            font-size: 0.9rem;
        }
        
        .nav-links {
            text-align: center;
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .nav-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .secondary-link {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .secondary-link:hover {
            background: #667eea;
            color: white;
        }
        
        @media (max-width: 768px) {
            .results-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
            }
            
            .container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🔍 Search Results</div>
            <div class="subtitle">Similar images found in your database</div>
        </div>
        
        <!-- @if(isset($allScores))
            <div style="margin: 20px 0; padding: 10px; background: #ffe; border: 1px solid #ccc; border-radius: 8px;">
                <h4>All Similarity Scores (for debugging):</h4>
                <ul>
                    @foreach($allScores as $score)
                        <li>
                            <b>{{ $score['image']->path }}</b>:
                            {{ number_format($score['similarity'] * 100, 2) }}%
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif -->
        
        @if(count($results) === 0)
            <div class="no-results">
                <div class="no-results-icon">🔍</div>
                <h3>No similar images found</h3>
                <p>Try uploading more images to the database or search with a different image.</p>
            </div>
        @else
            <div class="results-count">
                Found {{ count($results) }} similar image{{ count($results) > 1 ? 's' : '' }}
            </div>
            
            <div class="results-grid">
                @foreach($results as $result)
                    <div class="result-card">
                        <img src="{{ asset('storage/' . $result['image']->path) }}" 
                             alt="Similar image" 
                             class="result-image">
                        <div class="similarity-score">
                            {{ number_format($result['similarity'] * 100, 1) }}% Match
                        </div>
                        <div class="image-info">
                            Uploaded: {{ $result['image']->created_at->format('M d, Y') }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        
        <div class="nav-links">
            <a href="{{ route('images.search.form') }}" class="nav-link">🔍 New Search</a>
            <a href="{{ route('images.upload.form') }}" class="nav-link secondary-link">📤 Upload More</a>
        </div>
    </div>
</body>
</html>