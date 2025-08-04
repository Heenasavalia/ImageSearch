#!/usr/bin/env python3
"""
Test script to verify category detection is working correctly
"""

import sys
import os
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from extract_features_simple import get_feature_vector

def test_category_detection():
    """Test category detection on sample images"""
    
    # Test with a sample image if it exists
    test_image = "test_image.jpg"
    
    if not os.path.exists(test_image):
        print("No test image found. Please upload an image first.")
        return
    
    try:
        print("Testing category detection...")
        features = get_feature_vector(test_image)
        
        # Extract category scores (last 4 features)
        if len(features) >= 4:
            flower_score = features[-4]
            animal_score = features[-3]
            jewelry_score = features[-2]
            human_score = features[-1]
            
            print(f"\nCategory Scores:")
            print(f"Flower: {flower_score:.3f}")
            print(f"Animal: {animal_score:.3f}")
            print(f"Jewelry: {jewelry_score:.3f}")
            print(f"Human: {human_score:.3f}")
            
            # Find dominant category
            scores = [flower_score, animal_score, jewelry_score, human_score]
            categories = ['Flower', 'Animal', 'Jewelry', 'Human']
            max_index = scores.index(max(scores))
            
            print(f"\nDominant Category: {categories[max_index]} ({scores[max_index]:.3f})")
            
            # Check if scores are reasonable
            if max(scores) > 0.1:
                print("✅ Category detection appears to be working")
                
                # Provide guidance on what the scores mean
                print(f"\nInterpretation:")
                if flower_score > 0.3:
                    print(f"  - This image is likely a FLOWER (score: {flower_score:.3f})")
                if animal_score > 0.3:
                    print(f"  - This image is likely an ANIMAL (score: {animal_score:.3f})")
                if jewelry_score > 0.3:
                    print(f"  - This image is likely JEWELRY (score: {jewelry_score:.3f})")
                if human_score > 0.3:
                    print(f"  - This image is likely a HUMAN (score: {human_score:.3f})")
                    
            else:
                print("⚠️  Category scores are very low - may need adjustment")
                
        else:
            print("❌ Feature vector too short")
            
    except Exception as e:
        print(f"❌ Error: {e}")

if __name__ == "__main__":
    test_category_detection() 