import pandas as pd
from sklearn.ensemble import RandomForestRegressor
import joblib

print("เริ่มต้นกระบวนการเทรนโมเดลทำนายผลผลิตและกำไร...")

# 1. อ่านข้อมูลจาก CSV
try:
    data = pd.read_csv('dataset_yield.csv', encoding='utf-8', thousands=',')
    print(f"อ่านไฟล์ dataset_yield.csv สำเร็จ! พบข้อมูลทั้งหมด {len(data)} กรณีศึกษา")
except FileNotFoundError:
    print("Error: ไม่พบไฟล์ dataset_yield.csv")
    exit()

# *** จุดที่แก้ไข: เพิ่มขั้นตอนการจัดการข้อมูลที่หายไป (NaN) ***
print("ตรวจสอบและจัดการข้อมูลที่หายไป (NaN)...")
original_rows = len(data)
data.dropna(subset=['final_harvest_weight_kg', 'final_profit'], inplace=True)
cleaned_rows = len(data)

if cleaned_rows < original_rows:
    print(f"คำเตือน: ได้ลบข้อมูลจำนวน {original_rows - cleaned_rows} แถว เนื่องจากมีค่าว่างในคอลัมน์ผลลัพธ์")

if data.empty:
    print("Error: ไม่มีข้อมูลเหลือให้เทรนหลังจากลบแถวที่มีค่าว่างทิ้งไปแล้ว")
    exit()

# 2. เตรียมข้อมูล (Features และ Labels)
features = [
    'pond_size_rai', 'cycle_duration_days', 'initial_fish_amount', 
    'initial_fish_cost', 'total_food_sacks_811', 'total_food_sacks_812',
    'total_food_cost', 'other_expenses', 'prorated_rent_cost', 
    'avg_ph', 'avg_ammonium', 'avg_nitrite', 'water_problem_incidents', 'price_selling/Kg'
]
label_weight = 'final_harvest_weight_kg'
label_profit = 'final_profit'

missing_cols = [col for col in features + [label_weight, label_profit] if col not in data.columns]
if missing_cols:
    print(f"Error: ไม่พบคอลัมน์ต่อไปนี้ในไฟล์ CSV: {', '.join(missing_cols)}")
    exit()

X = data[features]
y_weight = data[label_weight]
y_profit = data[label_profit]

# 3. สร้างและสอนโมเดลสำหรับ "ทำนายน้ำหนัก"
print("กำลังเทรนโมเดลทำนาย 'น้ำหนักผลผลิต'...")
model_weight = RandomForestRegressor(n_estimators=100, random_state=42)
model_weight.fit(X, y_weight)
print("เทรนโมเดล 'น้ำหนักผลผลิต' สำเร็จ!")

# *** เพิ่มวัดความแม่นยำ ***
weight_score = model_weight.score(X, y_weight)
print(f"ความแม่นยำของโมเดล 'น้ำหนักผลผลิต' (R²): {weight_score:.4f}")

# 4. สร้างและสอนโมเดลสำหรับ "ทำนายกำไร"
print("กำลังเทรนโมเดลทำนาย 'กำไร/ขาดทุน'...")
model_profit = RandomForestRegressor(n_estimators=100, random_state=42)
model_profit.fit(X, y_profit)
print("เทรนโมเดล 'กำไร/ขาดทุน' สำเร็จ!")

# *** เพิ่มวัดความแม่นยำ ***
profit_score = model_profit.score(X, y_profit)
print(f"ความแม่นยำของโมเดล 'กำไร/ขาดทุน' (R²): {profit_score:.4f}")

# 5. บันทึกโมเดลทั้งสองเก็บไว้เป็นไฟล์
joblib.dump(model_weight, 'yield_weight_model.pkl')
joblib.dump(model_profit, 'yield_profit_model.pkl')

print("\nบันทึก 'สมอง AI' ทั้ง 2 ก้อนสำเร็จ!")
print("- yield_weight_model.pkl (สำหรับทำนายน้ำหนัก)")
print("- yield_profit_model.pkl (สำหรับทำนายกำไร)")
