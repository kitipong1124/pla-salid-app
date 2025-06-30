import joblib
import pandas as pd
import sys
import json

try:
    # 1. โหลดโมเดลและรายชื่อ Features ที่ใช้ตอนเทรน
    model = joblib.load('growth_model_v2.pkl')
    with open('growth_model_features_v2.json', 'r') as f:
        trained_features = json.load(f)

    # 2. รับ argument 3 ตัวตามที่ PHP ส่งมา
    if len(sys.argv) == 4: # 1 for script name + 3 features
        input_features_values = [float(arg) for arg in sys.argv[1:]]

        # 3. สร้าง DataFrame จากข้อมูลที่รับมา
        input_df = pd.DataFrame([input_features_values], columns=trained_features)

        # 4. ทำนายผล
        predicted_size = model.predict(input_df)

        # 5. ส่งผลลัพธ์กลับ
        result = { "success": True, "predicted_size": round(predicted_size[0], 2) }
    else:
        result = {'error': f'สคริปต์ต้องการ Argument 3 ตัว แต่ได้รับ {len(sys.argv) - 1}'}

except FileNotFoundError:
    result = {'error': 'ไม่พบไฟล์โมเดล (.pkl) หรือไฟล์ features (.json)'}
except Exception as e:
    result = {'error': f"เกิดข้อผิดพลาดใน Python: {str(e)}"}

# ส่งผลลัพธ์กลับไปให้ PHP ในรูปแบบ JSON
print(json.dumps(result, ensure_ascii=False))