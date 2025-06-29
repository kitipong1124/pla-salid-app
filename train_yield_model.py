import pandas as pd
from sklearn.ensemble import RandomForestRegressor
import joblib

print("เริ่มต้นกระบวนการเทรนโมเดลทำนายผลผลิตและกำไร...")

# 1. อ่านข้อมูลจาก CSV ที่เราเตรียมไว้
try:
    # ระบุ encoding เป็น 'utf-8' เพื่อรองรับภาษาไทยในชื่อบ่อ
    data = pd.read_csv('datatest.csv', encoding='utf-8')
    print(f"อ่านไฟล์ datatest.csv สำเร็จ! พบข้อมูลทั้งหมด {len(data)} กรณีศึกษา")
except FileNotFoundError:
    print("Error: ไม่พบไฟล์ datatest.csv กรุณาตรวจสอบว่าไฟล์อยู่ในตำแหน่งที่ถูกต้อง")
    exit()

# 2. เตรียมข้อมูล (Features และ Labels)
# Features คือ "ปัจจัย" ที่เราจะใช้ในการทำนาย (ตัดคอลัมน์ที่ไม่ใช่ตัวเลขและผลลัพธ์ออก)
features = [
    'pond_size_rai', 'cycle_duration_days', 'initial_fish_amount', 
    'initial_fish_cost', 'total_food_sacks_811', 'total_food_sacks_812',
    'total_food_cost', 'other_expenses', 'prorated_rent_cost', 
    'avg_ph', 'avg_ammonium', 'avg_nitrite', 'water_problem_incidents','price_selling/Kg'
]

# Labels คือ "คำตอบ" ที่เราต้องการให้ AI เรียนรู้ที่จะทำนาย
label_weight = 'final_harvest_weight_kg'
label_profit = 'final_profit'

X = data[features]
y_weight = data[label_weight]
y_profit = data[label_profit]

# 3. สร้างและสอนโมเดลสำหรับ "ทำนายน้ำหนัก"
print("กำลังเทรนโมเดลทำนาย 'น้ำหนักผลผลิต'...")
model_weight = RandomForestRegressor(n_estimators=100, random_state=42)
model_weight.fit(X, y_weight)
print("เทรนโมเดล 'น้ำหนักผลผลิต' สำเร็จ!")

# 4. สร้างและสอนโมเดลสำหรับ "ทำนายกำไร"
print("กำลังเทรนโมเดลทำนาย 'กำไร/ขาดทุน'...")
model_profit = RandomForestRegressor(n_estimators=100, random_state=42)
model_profit.fit(X, y_profit)
print("เทรนโมเดล 'กำไร/ขาดทุน' สำเร็จ!")

# 5. บันทึกโมเดลทั้งสองเก็บไว้เป็นไฟล์
joblib.dump(model_weight, 'yield_weight_model.pkl')
joblib.dump(model_profit, 'yield_profit_model.pkl')

print("\nบันทึก 'สมอง AI' ทั้ง 2 ก้อนสำเร็จ!")
print("- yield_weight_model.pkl (สำหรับทำนายน้ำหนัก)")
print("- yield_profit_model.pkl (สำหรับทำนายกำไร)")