import pandas as pd
from sklearn.ensemble import RandomForestRegressor
import joblib

print("--- เริ่มต้นเทรน 'AI ที่ปรึกษาการเจริญเติบโต' (v2) ---")

# 1. อ่านข้อมูลจาก CSV
try:
    data = pd.read_csv('dataset_size.csv', encoding='utf-8')
    data.dropna(subset=['rearing_day', 'fish_size', 'pond_size_rai', 'fish_amount'], inplace=True)
    print(f"อ่านและทำความสะอาดข้อมูลสำเร็จ! พบข้อมูล {len(data)} รายการ")
except Exception as e:
    print(f"Error: เกิดข้อผิดพลาดในการอ่านหรือเตรียมไฟล์ CSV: {e}")
    exit()

# 2. เตรียมข้อมูล
features = ['rearing_day', 'pond_size_rai', 'fish_amount']
target_label = 'fish_size'

X = data[features]
y = data[target_label]

print(f"Features ที่ใช้เทรน: {', '.join(features)}")

# 3. สร้างและสอนโมเดล AI
print("กำลังสอน AI...")
model = RandomForestRegressor(n_estimators=100, random_state=42)
model.fit(X, y)
print("สอน AI สำเร็จ!")

# 4. แสดงความแม่นยำ (Accuracy / R²)
accuracy = model.score(X, y)
print(f"ความแม่นยำของโมเดล (R² score): {accuracy:.4f}")

# 5. บันทึกโมเดลและรายชื่อ Features ที่ใช้
joblib.dump(model, 'growth_model_v2.pkl')
print("บันทึกโมเดลสำเร็จ! ไฟล์ 'growth_model_v2.pkl' ถูกสร้างขึ้นแล้ว")

feature_list_filename = 'growth_model_features_v2.json'
with open(feature_list_filename, 'w') as f:
    import json
    json.dump(features, f)
print(f"บันทึกรายชื่อ Features สำเร็จ! ไฟล์ '{feature_list_filename}' ถูกสร้างขึ้นแล้ว")
