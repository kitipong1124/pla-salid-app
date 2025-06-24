# 1. โหลดไลบรารีที่จำเป็น
import pandas as pd
from sklearn.tree import DecisionTreeClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score
import joblib

print("เริ่มต้นกระบวนการเทรนโมเดล AI...")

# 2. อ่านข้อมูลจากไฟล์ CSV ที่เราเตรียมไว้
try:
    data = pd.read_csv('training_data.csv')
    print("อ่านไฟล์ training_data.csv สำเร็จ")
except FileNotFoundError:
    print("เกิดข้อผิดพลาด: ไม่พบไฟล์ training_data.csv กรุณาตรวจสอบว่าไฟล์อยู่ในตำแหน่งที่ถูกต้อง")
    exit()

# 3. เตรียมข้อมูลสำหรับเทรน
# แยก "คำใบ้" (Features) และ "คำตอบ" (Label) ออกจากกัน
features = ['ph', 'ammonium', 'nitrite']
target = 'label_encoded'

X = data[features]  # ข้อมูลคำใบ้
y = data[target]    # ข้อมูลคำตอบที่ถูกต้อง

# 4. แบ่งข้อมูลออกเป็น "ชุดสำหรับสอน" (80%) และ "ชุดสำหรับสอบ" (20%)
# random_state=42 คือการกำหนดให้การสุ่มมีรูปแบบเดิมทุกครั้งเพื่อให้ผลลัพธ์คงที่
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
print(f"แบ่งข้อมูล: {len(X_train)} ชุดสำหรับสอน, {len(X_test)} ชุดสำหรับสอบ")

# 5. สร้างและสอนโมเดล "ต้นไม้ตัดสินใจ" (Decision Tree)
# เราสามารถปรับ parameter ภายใน DecisionTreeClassifier() ได้ในอนาคต
model = DecisionTreeClassifier(random_state=42)

print("กำลังสอน AI ด้วยข้อมูลชุดสำหรับสอน...")
model.fit(X_train, y_train)
print("สอน AI เสร็จสิ้น!")

# 6. (ไม่บังคับแต่น่าสนใจ) ทดสอบความแม่นยำของโมเดล
# ให้โมเดลลองทำ "ข้อสอบ" ที่เราแบ่งไว้
predictions = model.predict(X_test)
accuracy = accuracy_score(y_test, predictions)
print(f"ผลการทดสอบ: โมเดลมีความแม่นยำประมาณ {accuracy * 100:.2f}%")

# 7. บันทึกโมเดลที่ฉลาดแล้วเก็บไว้เป็นไฟล์
# ไฟล์นี้คือ "สมอง" ของ AI ที่เราจะนำไปใช้งานจริง
model_filename = 'water_quality_model.pkl'
joblib.dump(model, model_filename)
print(f"บันทึกโมเดลสำเร็จ! ไฟล์ '{model_filename}' ถูกสร้างขึ้นแล้ว")