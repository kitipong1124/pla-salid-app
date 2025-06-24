import joblib
import pandas as pd
import sys

# 1. โหลดโมเดลที่เทรนไว้แล้ว และเตรียม "พจนานุกรม"
try:
    model = joblib.load('water_quality_model.pkl')
except FileNotFoundError:
    print("Error: ไม่พบไฟล์โมเดล 'water_quality_model.pkl'")
    sys.exit(1)

label_map = {
    0: "สภาวะปกติ (Optimal Condition)",
    1: "ควรเฝ้าระวัง (Early Warning)",
    2: "ปลาเริ่มเครียด/กินอาหารลดลง (Stress/Reduced Feeding)",
    3: "อันตรายสูง/เสี่ยงต่อการตาย (High Danger/Mortality Risk)"
}

# 2. รับข้อมูลจาก PHP และทำการทำนาย
if len(sys.argv) == 4:
    try:
        ph = float(sys.argv[1])
        ammonium = float(sys.argv[2])
        nitrite = float(sys.argv[3])
        
        input_data = pd.DataFrame([[ph, ammonium, nitrite]], columns=['ph', 'ammonium', 'nitrite'])
        prediction_encoded = model.predict(input_data)
        prediction_text = label_map.get(prediction_encoded[0], "ไม่สามารถระบุผลได้")
        
        # ส่งคืนผลลัพธ์ที่เป็นภาษาไทยกลับไป
        print(prediction_text)

    except ValueError:
        print("Error: ข้อมูลที่ส่งมาต้องเป็นตัวเลขเท่านั้น")
        sys.exit(1)
else:
    print("Error: กรุณาส่งค่า pH, ammonium, และ nitrite มาให้ครบ")
    sys.exit(1)