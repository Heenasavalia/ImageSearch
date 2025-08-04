<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Image Search Engine</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-result {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .category-scores {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 10px 0;
        }
        .category-item {
            background: #e9ecef;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Debug Panel</h1>
        
        <div class="test-result">
            <h3>Test Similarity Calculation</h3>
            <p>This will test the similarity calculation between two random images in your database.</p>
            <button class="btn" onclick="testSimilarity()">Test Similarity</button>
            <div id="testResult"></div>
        </div>
        
        <div class="test-result">
            <h3>Debug Category Detection</h3>
            <p>This will show the category detection results for all images in your database.</p>
            <button class="btn" onclick="debugCategories()">Debug Categories</button>
            <div id="categoryResult"></div>
        </div>
        
        <div class="test-result">
            <h3>Re-extract Features</h3>
            <p>This will reprocess all existing images with the improved algorithm.</p>
            <a href="{{ route('images.re-extract') }}" class="btn" onclick="return confirm('This will re-process all images. Continue?')">Re-extract Features</a>
        </div>
        
        <div class="test-result">
            <h3>Quick Actions</h3>
            <a href="{{ route('images.upload.form') }}" class="btn">Upload Images</a>
            <a href="{{ route('images.search.form') }}" class="btn">Search Images</a>
        </div>
    </div>

    <script>
        function testSimilarity() {
            fetch('/images/test-similarity')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('testResult').innerHTML = 
                            `<div class="error"><strong>Error:</strong> ${data.error}</div>`;
                    } else {
                        let html = `
                            <div class="success">
                                <h4>Test Results:</h4>
                                <p><strong>Image 1:</strong> ${data.image1}</p>
                                <p><strong>Image 2:</strong> ${data.image2}</p>
                                <p><strong>Similarity Score:</strong> ${(data.similarity * 100).toFixed(1)}%</p>
                                <p><strong>Category 1:</strong> ${data.category1}</p>
                                <p><strong>Category 2:</strong> ${data.category2}</p>
                                
                                <h5>Category Scores:</h5>
                                <div class="category-scores">
                                    <div>
                                        <strong>Image 1 (${data.image1}):</strong>
                                        ${Object.entries(data.all_scores1).map(([cat, score]) => 
                                            `<div class="category-item">${cat}: ${score.toFixed(3)}</div>`
                                        ).join('')}
                                    </div>
                                    <div>
                                        <strong>Image 2 (${data.image2}):</strong>
                                        ${Object.entries(data.all_scores2).map(([cat, score]) => 
                                            `<div class="category-item">${cat}: ${score.toFixed(3)}</div>`
                                        ).join('')}
                                    </div>
                                </div>
                            </div>
                        `;
                        document.getElementById('testResult').innerHTML = html;
                    }
                })
                .catch(error => {
                    document.getElementById('testResult').innerHTML = 
                        `<div class="error"><strong>Error:</strong> ${error.message}</div>`;
                });
        }
        
        function debugCategories() {
            fetch('/images/debug-categories')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('categoryResult').innerHTML = 
                            `<div class="error"><strong>Error:</strong> ${data.error}</div>`;
                    } else {
                        let html = `
                            <div class="success">
                                <h4>Category Detection Results:</h4>
                                <p><strong>Total Images:</strong> ${data.length}</p>
                                ${data.map(item => `
                                    <div class="category-item" style="margin: 5px 0; padding: 10px; background: #e9ecef;">
                                        <strong>${item.image}</strong> → ${item.category} (${item.score})
                                        <br><small>
                                            Flower: ${item.all_scores.FLOWER}, 
                                            Animal: ${item.all_scores.ANIMAL}, 
                                            Jewelry: ${item.all_scores.JEWELRY}, 
                                            Human: ${item.all_scores.HUMAN}
                                        </small>
                                    </div>
                                `).join('')}
                            </div>
                        `;
                        document.getElementById('categoryResult').innerHTML = html;
                    }
                })
                .catch(error => {
                    document.getElementById('categoryResult').innerHTML = 
                        `<div class="error"><strong>Error:</strong> ${error.message}</div>`;
                });
        }
    </script>
</body>
</html> 