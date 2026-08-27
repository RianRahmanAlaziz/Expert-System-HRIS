# Expert System HRIS

Web-based **Human Resource Information System (HRIS)** yang dilengkapi dengan **Expert System** untuk membantu HR dalam mengelola data karyawan, proses HR, serta memberikan rekomendasi berdasarkan knowledge base dan rule yang telah ditentukan.

Project ini menggunakan:

* **Backend:** Laravel
* **Frontend:** Next.js
* **Database:** MySQL
* **API:** REST API
* **Authentication:** Laravel Sanctum

---

## 🎯 Tujuan

Expert System HRIS dibuat untuk membantu perusahaan dalam:

* Mengelola data karyawan secara terpusat
* Mengelola struktur organisasi
* Mengelola absensi dan cuti
* Mengelola performance karyawan
* Mengelola kompetensi dan skill
* Mengelola training dan career development
* Membantu proses pengambilan keputusan HR
* Memberikan rekomendasi berdasarkan rule dan data karyawan

Sistem ini tidak menggantikan keputusan HR, tetapi berfungsi sebagai **Decision Support System**.

---

## 🚀 Fitur

### Authentication & Authorization

* Login / Logout
* User Management
* Role & Permission
* Profile Management
* Activity Log

### Employee Management

* Employee CRUD
* Employee Profile
* Department
* Position
* Employment Status
* Employment History
* Manager / Reporting Structure

### Attendance

* Clock In / Clock Out
* Attendance History
* Late Tracking
* Attendance Summary
* Attendance Report

### Leave Management

* Leave Type
* Leave Balance
* Leave Request
* Approval / Rejection
* Leave History

### Performance

* Performance Period
* KPI / Indicator
* Performance Review
* Performance Score
* Performance History

### Competency

* Competency Master
* Competency Level
* Employee Competency
* Competency Assessment
* Competency Gap

### Training

* Training Management
* Training Participant
* Training History
* Training Recommendation

### Career

* Career Path
* Promotion Assessment
* Readiness Score
* Career Recommendation

---

# 🧠 Expert System

Expert System merupakan fitur utama yang membedakan project ini dari HRIS biasa.

Alur sistem:

```text
Employee Data
      ↓
Knowledge Base
      ↓
Rules
      ↓
Rule Conditions
      ↓
Inference Engine
      ↓
Scoring
      ↓
Recommendation
      ↓
Explanation
```

### Knowledge Base

Menyimpan pengetahuan HR yang digunakan sebagai dasar pengambilan keputusan.

Contoh:

```text
Promotion Eligibility

Performance >= 85
Competency >= 80
Experience >= 3 years
Attendance >= 90%
```

### Rule Engine

Menggunakan aturan berbasis:

```text
IF condition
AND condition
THEN recommendation
```

Contoh:

```text
IF performance >= 85
AND competency >= 80
AND experience >= 3
THEN recommended_for_promotion
```

### Consultation

HR dapat memilih:

* Employee
* Assessment Type
* Assessment Criteria

Kemudian sistem menjalankan proses inferensi dan menghasilkan rekomendasi.

### Recommendation

Contoh:

```text
Recommendation:
Recommended for Promotion

Score:
87 / 100

Confidence:
91%

Reason:
Employee memenuhi seluruh kriteria utama
untuk rekomendasi promosi.
```

---

# 🏗️ Architecture

```text
┌──────────────────────┐
│      Next.js FE      │
│      TypeScript      │
└──────────┬───────────┘
           │
        REST API
           │
           ▼
┌──────────────────────┐
│    Laravel Backend   │
│                      │
│ Controllers          │
│ Services             │
│ Policies             │
│ Expert Engine        │
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│       MySQL          │
└──────────────────────┘
```

---

# 📦 Backend Domain

Backend dibagi menjadi beberapa domain:

```text
Authentication
Organization
Employee
Attendance
Leave
Performance
Competency
Training
Career
Expert System
Notification
Reporting
Activity Log
```

---

# 🗄️ Database

Model utama:

```text
User
Department
Position
Employee

Attendance

LeaveType
LeaveRequest

PerformancePeriod
PerformanceIndicator
PerformanceReview
PerformanceReviewItem

Competency
CompetencyLevel
EmployeeCompetency

Training
TrainingParticipant

KnowledgeCategory
Knowledge
ExpertRule
RuleCondition
RuleAction

ExpertConsultation
ConsultationResult

Notification
ActivityLog
```

---

# 📁 Backend Structure

```text
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Resources/
│   │
│   ├── Models/
│   ├── Services/
│   ├── Policies/
│   ├── Actions/
│   └── ExpertSystem/
│
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
│
├── routes/
│   └── api.php
│
└── tests/
```

---

# 📁 Frontend Structure

```text
frontend/
├── app/
├── components/
├── features/
│   ├── auth/
│   ├── employees/
│   ├── attendance/
│   ├── leave/
│   ├── performance/
│   ├── competency/
│   ├── training/
│   ├── career/
│   └── expert-system/
│
├── lib/
├── hooks/
├── services/
├── types/
└── constants/
```

---

# ⚙️ Installation

## Backend

Clone repository:

```bash
git clone <repository-url>
cd backend
```

Install dependencies:

```bash
composer install
```

Copy environment:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

Configure database pada `.env`.

Run migration:

```bash
php artisan migrate
```

Run seeder:

```bash
php artisan db:seed
```

Start server:

```bash
php artisan serve
```

---

## Frontend

Masuk ke directory frontend:

```bash
cd frontend
```

Install dependencies:

```bash
npm install
```

Configure environment:

```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

Run development server:

```bash
npm run dev
```

Frontend tersedia di:

```text
http://localhost:3000
```

---

# 🔐 Environment

Backend:

```env
APP_NAME="Expert System HRIS"
APP_ENV=local
APP_KEY=
APP_DEBUG=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hris
DB_USERNAME=root
DB_PASSWORD=
```

Frontend:

```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

---

# 🧪 Testing

Backend:

```bash
php artisan test
```

Frontend:

```bash
npm run lint
```

---

# 🛣️ Development Roadmap

### Phase 1 — Foundation

* [ ] Laravel setup
* [ ] Database setup
* [ ] Authentication
* [ ] Role & Permission
* [ ] User Management

### Phase 2 — Organization

* [ ] Department
* [ ] Position
* [ ] Employee
* [ ] Organization Structure

### Phase 3 — HR Core

* [ ] Attendance
* [ ] Leave
* [ ] Performance
* [ ] Competency
* [ ] Training
* [ ] Career

### Phase 4 — Expert System

* [ ] Knowledge Category
* [ ] Knowledge Base
* [ ] Expert Rules
* [ ] Rule Conditions
* [ ] Rule Actions
* [ ] Inference Engine
* [ ] Scoring
* [ ] Consultation
* [ ] Recommendation
* [ ] Explanation

### Phase 5 — Supporting Features

* [ ] Notification
* [ ] Activity Log
* [ ] Reporting
* [ ] Dashboard
* [ ] Export Report

### Phase 6 — Advanced

* [ ] Advanced Analytics
* [ ] AI HR Assistant
* [ ] Predictive Analytics
* [ ] Advanced Employee Risk Analysis

---

# 🎯 Development Principle

Project ini dikembangkan secara bertahap dengan prinsip:

> **Understand → Design → Implement → Test → Review → Refactor**

Setiap fitur harus dipahami terlebih dahulu sebelum implementasi.

Khusus Backend, pengembangan dimulai dari:

```text
Migration
    ↓
Model
    ↓
Relationship
    ↓
Factory / Seeder
    ↓
Request Validation
    ↓
Resource
    ↓
Service
    ↓
Controller
    ↓
Route
    ↓
Testing
```

Tujuan utama project bukan hanya menghasilkan aplikasi HRIS, tetapi membangun sistem yang **maintainable, scalable, testable, dan mudah dikembangkan**.

---

# 📌 Status

**Current Status:** Development

**Backend:** Laravel — In Development

**Frontend:** Next.js — In Development

**Expert System:** Planned / In Development

---

## 👨‍💻 Project Focus

**HRIS + Expert System + HR Decision Support System**

> Mengubah data HR menjadi insight dan rekomendasi yang dapat membantu HR mengambil keputusan secara lebih terstruktur dan berbasis data.
