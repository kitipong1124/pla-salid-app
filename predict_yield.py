import joblib
import pandas as pd
import sys
import json

# ไฟล์นี้จะรับ "ชื่อไฟล์" ของจดหมายเป็น argument ตัวเดียว
if len(sys.argv) != 2:
    print(json.dumps({'error': 'สคริปต์ Python ต้องการชื่อไฟล์เป็น argument'}))
    sys.exit(1)

input_file_path = sys.argv[1]

try:
    # 1. อ่าน "จดหมาย" (ไฟล์ JSON) ที่ PHP สร้างขึ้น
    with open(input_file_path, 'r', encoding='utf-8') as f:
        input_features = json.load(f)

    # 2. โหลดโมเดลทั้ง 2 ตัว
    model_weight = joblib.load('yield_weight_model.pkl')
    model_profit = joblib.load('yield_profit_model.pkl')

    # 3. สร้าง DataFrame จากข้อมูลที่อ่านได้
    feature_names = [
        'pond_size_rai', 'cycle_duration_days', 'initial_fish_amount', 
        'initial_fish_cost', 'total_food_sacks_811', 'total_food_sacks_812',
        'total_food_cost', 'other_expenses', 'prorated_rent_cost', 
        'avg_ph', 'avg_ammonium', 'avg_nitrite', 'water_problem_incidents', 'price_selling/Kg'
    ]
    input_df = pd.DataFrame([input_features], columns=feature_names)

    # 4. ทำนายผลลัพธ์
    predicted_weight = model_weight.predict(input_df)
    predicted_profit = model_profit.predict(input_df)

    # 5. สร้างผลลัพธ์เพื่อส่งกลับ
    result = {
        "success": True,
        "predicted_weight": round(predicted_weight[0], 2),
        "predicted_profit": round(predicted_profit[0], 2)
    }

except Exception as e:
    result = {'error': f"เกิดข้อผิดพลาดใน Python: {str(e)}"}

# 6. ส่งผลลัพธ์กลับไปให้ PHP ในรูปแบบ JSON
print(json.dumps(result, ensure_ascii=False))