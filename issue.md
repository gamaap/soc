# Issue: Perbaikan Report Vehicle Pass + Pencatatan Barang Keluar (Delivery & Visitor)

> **Untuk implementor (junior dev / AI model):** Baca dulu `AGENTS.md` di root. Project ini **Laravel 12 + Livewire Volt v4 + Flux UI v2**. Halaman dashboard adalah file Volt single-file di `resources/views/pages/dashboard/` (prefix `⚡`). Ikuti konvensi file yang sudah ada. Setelah mengubah PHP, jalankan `vendor/bin/pint --dirty --format agent`. Setiap perubahan **wajib** disertai test (Pest) dan dijalankan dengan `php artisan test --compact --filter=...`.

Ada **3 pekerjaan** di issue ini. Kerjakan berurutan, commit terpisah per task.

---

## Task 1 — BUG: Report Vehicle Pass tidak menampilkan semua record

### Akar masalah (sudah dianalisa)
File `resources/views/pages/dashboard/⚡report.blade.php` method `getVehiclePassRecords()` membaca dari model **`VehiclePass`** (tabel `vehicle_passes`). **Tabel itu tidak pernah diisi oleh aplikasi** — hanya diisi oleh `database/seeders/VehiclePassSeeder.php`. Data vehicle pass yang sebenarnya tersebar di **3 sumber** (lihat `⚡vehicle-pass.blade.php`):

| Sumber | Model | Catatan |
|---|---|---|
| Company Vehicle Pass | `App\Models\SuperappCarDriverRequest` | Koneksi DB eksternal `superapp`, tabel `rrs.r_car_driver_requests`. Punya KM, destination, purpose. |
| Employee Pass | `App\Models\EmployeePass` | Tabel `employee_passes`. |
| Other Pass | `App\Models\OtherPass` | Tabel `other_passes`. |

Karena itu report hanya menampilkan data seeder (atau kosong di produksi).

### Keputusan desain (sudah dikonfirmasi user)
**Gabungkan ketiga sumber menjadi satu daftar** di kategori `vehicle_pass`. Tambahkan kolom **Type** (Company / Employee / Other) di paling depan agar baris bisa dibedakan. Field yang tidak relevan untuk suatu jenis diisi `-`.

### Mapping kolom (normalisasi tiap sumber ke bentuk yang sama)

| Kolom Report | Company (`SuperappCarDriverRequest`) | Employee (`EmployeePass`) | Other (`OtherPass`) |
|---|---|---|---|
| Type | `Company` | `Employee` | `Other` |
| Date | `date` | `date` | `date` |
| Name | gabungan nama driver (`drivers.driver.name`, join `, `) | `name` | `name` |
| License Plate | `car.car_vehicle_number` | `license_plate` | `license_plate` |
| Purpose | `purpose.code` | `-` | `purpose` |
| Destination | gabungan `destinations.city` (buang nilai `-`) | `-` | `-` |
| Starting KM | `starting_km` | `-` | `-` |
| Ending KM | `ending_km` | `-` | `-` |
| Leaving Time | `security_departed_time` (format `H:i`) | `entry_time` | `leaving_time` |
| Return Time | `security_returned_time` (format `H:i`) | `leaving_time` | `entry_time` |

> Catatan: untuk Employee/Other, "Leaving Time" = waktu kendaraan masuk fasilitas dan "Return Time" = waktu keluar fasilitas, mengikuti urutan yang sudah dipakai di halaman vehicle pass.

### Langkah implementasi (`⚡report.blade.php`)

1. **Tambahkan import** model di blok `use` paling atas:
   ```php
   use App\Models\SuperappCarDriverRequest;
   use App\Models\EmployeePass;
   use App\Models\OtherPass;
   ```

2. **Ganti `getVehiclePassRecords()`** supaya membangun `Collection` ter-normalisasi dari 3 sumber, lalu digabung dan diurutkan. Kembalikan koleksi berisi object (`(object) [...]`) dengan key konsisten: `type, date, name, license_plate, purpose, destination, starting_km, ending_km, leaving_time, return_time`. Terapkan filter tanggal yang sama (`dateFrom`, `dateTo`, `month`) pada masing-masing query **sebelum** `get()`.
   - Company: filter `whereIn('status', ['Assigned','In Transit','Completed'])`, `where('plant_id', 1)`, dan hanya yang sudah berangkat (`whereNotNull('security_departed_time')`). Eager load `drivers.driver`, `car`, `purpose`, `destinations`.
   - Gunakan helper kecil untuk menerapkan filter tanggal pada kolom `date` (mirip `applyCommonFilters`, tapi tanpa department).
   - Setelah ketiga koleksi dipetakan ke object, gabung dengan `->merge()` dan urutkan: `->sortByDesc('date')` lalu (opsional) by `leaving_time` desc. Akhiri dengan `->values()`.

3. **Update `columns()`** untuk `vehicle_pass`: tambahkan `'Type'` di depan:
   ```php
   'vehicle_pass' => ['Type', 'Date', 'Name', 'License Plate', 'Purpose', 'Destination', 'Starting KM', 'Ending KM', 'Leaving Time', 'Return Time'],
   ```

4. **Update blok render tabel** `@case('vehicle_pass')` (sekitar baris 505): tambahkan cell `Type` paling depan dan baca dari object ternormalisasi (`$record->type`, `$record->name`, dst). Karena `leaving_time`/`return_time` sudah string `H:i`, tampilkan langsung dengan fallback `?: '-'`. Format `date` dengan `Carbon::parse($record->date)->format('d/m/Y')`.

5. **Update `export()`** bagian `match` untuk `'vehicle_pass'` supaya membaca key yang sama dengan kolom baru (tambahkan `type`, dan jangan pakai `Carbon::parse($record->leaving_time)` lagi karena sudah `H:i` — cukup `$record->leaving_time ?: '-'`).

6. **Update `categoryCounts()` dan `hasRecords()`**: angka untuk `vehicle_pass` saat ini `VehiclePass::count()` → ganti jadi jumlah gabungan 3 sumber (Company yang sudah berangkat + EmployeePass + OtherPass) supaya kartu hitung & cek "ada record" konsisten. Boleh dibuat helper privat `vehiclePassBaseCount()`.

7. Model `VehiclePass` & `VehiclePassSeeder` **boleh dibiarkan** (jangan dihapus tanpa approval) — cukup tidak lagi dipakai di report.

### Hal yang harus diperhatikan
- `SuperappCarDriverRequest` memakai **koneksi DB lain (`superapp`)**. `whereYear`/`whereMonth` pada kolom `date` harus tetap jalan; kalau `date` bertipe string, filter `whereDate`/`whereYear` umumnya tetap berfungsi di MySQL. Uji manual.
- Jangan memanggil relasi cross-connection di dalam `whereHas` — cukup eager load lalu map di PHP.
- Hindari N+1: gunakan eager loading seperti di `⚡vehicle-pass.blade.php` (`vehiclePasses()`), boleh dijadikan referensi.

### Test (Task 1)
Buat `tests/Feature/Dashboard/VehiclePassReportTest.php`:
- Seed beberapa `EmployeePass` dan `OtherPass` pada bulan berjalan → render Volt report dengan `category = 'vehicle_pass'` → assert jumlah baris = jumlah record (Employee + Other) dan nama-namanya muncul.
- Untuk sumber Company (koneksi `superapp`) yang sulit di-test karena DB eksternal: cukup pastikan kode tidak error saat sumber itu kosong. Boleh ditandai sebagai catatan kalau koneksi `superapp` tidak tersedia di environment test.

---

## Task 2 — FITUR: Delivery mencatat barang yang dibawa keluar saat exit

### Tujuan
Saat tombol **Record Exit** pada modul Delivery ditekan, **jangan langsung** mencatat waktu keluar. Tampilkan modal yang menanyakan: **"Apakah tamu keluar membawa barang?"**
- **Tidak** → catat `exit_time` saja seperti sekarang.
- **Ya** → tampilkan input barang (Item Name, Quantity, UoM), lalu simpan barang tersebut + catat `exit_time`.
- Barang keluar ini ditampilkan di **modal View** (action "View") pada section baru: **"Items Taken Out"**.

### Data model
Tabel `delivery_items` sudah ada (dipakai untuk barang **masuk**). Tambahkan kolom penanda arah.

1. Buat migration:
   ```
   php artisan make:migration add_direction_to_delivery_items_table
   ```
   Isi: tambahkan kolom string `direction` default `'in'` (nilai: `in` = barang masuk, `out` = barang keluar). Karena ada default, baris lama otomatis `in`.

2. Update `app/Models/Delivery.php` — tambah relasi terpisah (pertahankan `items()` yang lama):
   ```php
   public function entryItems(): HasMany
   {
       return $this->hasMany(DeliveryItems::class)->where('direction', 'in');
   }

   public function exitItems(): HasMany
   {
       return $this->hasMany(DeliveryItems::class)->where('direction', 'out');
   }
   ```
   Pastikan pembuatan item saat entry (di `⚡delivery.blade.php` `save()` dan `⚡delivery-manual-entry.blade.php`) tetap menghasilkan `direction = 'in'` (cukup andalkan default, atau set eksplisit `'direction' => 'in'`).

### UI / Logika (`resources/views/pages/dashboard/⚡delivery.blade.php`)
1. Tambah state:
   ```php
   public $exitDeliveryId = null;
   public $exitHasItems = false;
   public $exitItems = [];
   ```
2. Ganti perilaku tombol exit. Tambah method:
   - `openExitModal($id)`: set `exitDeliveryId`, reset `exitHasItems = false`, set `exitItems = [['item_name'=>'','quantity'=>'','uom'=>'']]`, lalu `Flux::modal('record-exit')->show();`
   - `addExitItem()` / `removeExitItem($index)`: sama polanya dengan `addItem()/removeItem()`.
   - `confirmExit()`:
     ```php
     $delivery = Delivery::findOrFail($this->exitDeliveryId);
     $delivery->update([
         'exit_time' => Carbon::now()->format('H:i:s'),
         'updated_by' => auth()->id(),
     ]);
     if ($this->exitHasItems) {
         foreach ($this->exitItems as $item) {
             if (blank($item['item_name'])) { continue; }
             $delivery->items()->create([
                 'item_name' => $item['item_name'],
                 'quantity'  => $item['quantity'] ?: 0,
                 'uom'       => $item['uom'],
                 'direction' => 'out',
             ]);
         }
     }
     $this->reset(['exitDeliveryId', 'exitHasItems', 'exitItems']);
     Flux::modal('record-exit')->close();
     ```
   - Method `recordExit($id)` lama boleh dihapus (diganti `openExitModal`).
3. Di tabel, ubah tombol **Record Exit** agar memanggil `openExitModal({{ $delivery->id }})`.
4. Tambah modal baru `record-exit` (`:dismissible="false"`):
   - Pertanyaan "Apakah tamu membawa barang keluar?" pakai `flux:radio.group` / toggle ke `wire:model.live="exitHasItems"` (Ya/Tidak).
   - `@if($exitHasItems)` tampilkan baris item (Item Name / Quantity / UoM) — **reuse** daftar opsi UoM yang sama dengan modal entry (Pcs, Meters, Kgs, Sacks, Kaleng, Liters, Coils, Carries, Box, Bucket) dan tombol Add/Remove item.
   - Tombol submit `wire:click="confirmExit"` berlabel "Record Exit".
5. **View modal** (modal `view-delivery` di file ini): ubah loop barang masuk memakai `$this->delivery->entryItems`, lalu tambah section baru **"Items Taken Out"** yang me-loop `$this->delivery->exitItems` (tampilkan hanya jika tidak kosong). Pastikan computed `delivery()` meng-eager-load: `Delivery::with(['entryItems','exitItems'])->find(...)`.

### Update Report (`⚡report.blade.php`)
Modal `view-delivery` di report juga menampilkan items. Update sama: loop entry items pakai `entryItems`, tambah section "Items Taken Out" pakai `exitItems`, dan ubah computed `delivery()` menjadi `Delivery::with(['entryItems','exitItems'])->find($this->deliveryId)`.

### Test (Task 2)
`tests/Feature/Dashboard/DeliveryExitTest.php`:
- Exit **tanpa** barang → `exit_time` terisi, tidak ada `delivery_items` `direction=out`.
- Exit **dengan** barang → `exit_time` terisi, ada `delivery_items` `direction=out` sesuai jumlah item; item entry lama tetap `direction=in`.

---

## Task 3 — FITUR: Visitor mencatat barang yang dibawa keluar saat exit

Sama persis polanya dengan Task 2, **bedanya Visitor belum punya tabel item sama sekali** → buat baru. Visitor hanya mencatat barang **keluar** (tidak ada barang masuk), jadi tidak perlu kolom `direction`.

### Data model
1. Migration + model:
   ```
   php artisan make:model VisitorItems -m
   ```
   Tabel `visitor_items`: `id`, `foreignId('visitor_id')->constrained()->cascadeOnDelete()`, `string('item_name')`, `integer('quantity')->default(0)`, `string('uom')`, `timestamps()`. (Lihat `delivery_items` sebagai contoh.)
2. Model `App\Models\VisitorItems`:
   ```php
   protected $guarded = [];
   public function visitor(): BelongsTo { return $this->belongsTo(Visitor::class); }
   ```
3. `app/Models/Visitor.php` tambah relasi:
   ```php
   public function items(): HasMany { return $this->hasMany(VisitorItems::class); }
   ```

### UI / Logika (`resources/views/pages/dashboard/⚡visitor.blade.php`)
Sama dengan Task 2:
- State `exitVisitorId`, `exitHasItems`, `exitItems`.
- `openExitModal($id)`, `addExitItem()`, `removeExitItem($index)`, `confirmExit()` (set `exit_time` + `updated_by`, lalu kalau `exitHasItems` buat `VisitorItems` via `$visitor->items()->create([...])`, skip item yang `item_name` kosong).
- Hapus/ubah `recordExit($id)` lama menjadi `openExitModal`.
- Tabel: tombol **Record Exit** → `openExitModal`.
- Modal baru `record-exit` (Ya/Tidak + baris item, opsi UoM sama).
- **View modal** `view-visitor`: tambah section baru **"Items Taken Out"** yang me-loop `$this->visitor->items` (tampilkan jika tidak kosong). Eager load computed `visitor()` jadi `Visitor::with('items')->find(...)`.

### Update Report (`⚡report.blade.php`)
Modal `view-visitor` di report: tambah section "Items Taken Out" me-loop `$this->visitor->items`, dan ubah computed `visitor()` jadi `Visitor::with('items')->find($this->visitorId)`.

### Test (Task 3)
`tests/Feature/Dashboard/VisitorExitTest.php`:
- Exit tanpa barang → `exit_time` terisi, `visitor_items` kosong.
- Exit dengan barang → `exit_time` terisi, `visitor_items` terbuat sesuai jumlah item.

---

## Checklist sebelum selesai (semua task)
- [ ] `vendor/bin/pint --dirty --format agent` bersih.
- [ ] `php artisan test --compact` (minimal test baru lulus).
- [ ] Tidak ada perubahan dependency tanpa approval (lihat `AGENTS.md`).
- [ ] Jalankan `php artisan migrate` di lokal dan pastikan migration baru jalan.
- [ ] Frontend: ingatkan user untuk `npm run build` / `npm run dev` jika perubahan blade tidak muncul.

## Catatan polish (opsional, jika sempat)
- Beberapa empty-state `colspan` di tabel delivery/visitor masih `7` padahal kolom sudah 9 — boleh dirapikan, tapi bukan prioritas.
