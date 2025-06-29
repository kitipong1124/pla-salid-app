import joblib
import pandas as pd
import sys
import json

# 1. โหลดโมเดลและ Features เวอร์ชันใหม่
try:
    model = joblib.load('growth_model_v2.pkl')
    with open('growth_model_features_v2.json', 'r') as f:
        trained_features = json.load(f)
except FileNotFoundError:
    print(json.dumps({'error': 'ไม่พบไฟล์โมเดลเวอร์ชันล่าสุด (.pkl) หรือไฟล์ features (.json)'}))
    sys.exit(1)

# 2. รับ argument 3 ตัวตามที่ PHP ส่งมา
if len(sys.argv) == 4: # 1 for script name + 3 for features
    try:
        # แปลง arguments ที่ได้รับมาเป็นตัวเลข
        input_features_values = [float(arg) for arg in sys.argv[1:]]
        
        # สร้าง DataFrame จากข้อมูลที่รับมาโดยใช้รายชื่อ Features ที่ถูกต้อง
        input_df = pd.DataFrame([input_features_values], columns=trained_features)

        # 4. ทำนายผล
        predicted_size = model.predict(input_df)

        # 5. ส่งผลลัพธ์กลับในรูปแบบ JSON
        result = {
            "success": True,
            "predicted_size": round(predicted_size[0], 2)
        }
        print(json.dumps(result))

    except ValueError:
        print(json.dumps({'error': 'ข้อมูลที่ส่งมาต้องเป็นตัวเลขเท่านั้น'}))
        sys.exit(1)
else:
    print(json.dumps({'error': f'สคริปต์ต้องการ Argument 3 ตัว แต่ได้รับ {len(sys.argv) - 1}'}))
    sys.exit(1)