# Issue: Fitur Print Preview & Print Formulir Penerimaan Tamu (Modul Visitor)

> **Untuk implementor (junior dev / AI model):** Baca dulu `AGENTS.md` di root. Project ini **Laravel 12 + Livewire Volt v4 + Flux UI v2 + Tailwind v4**. Halaman dashboard adalah file Volt single-file di `resources/views/pages/dashboard/` (prefix `⚡`). Ikuti konvensi file yang sudah ada. Setelah mengubah PHP, jalankan `vendor/bin/pint --dirty --format agent`. Setiap perubahan **wajib** disertai test (Pest) dan dijalankan dengan `php artisan test --compact --filter=...`.

## Tujuan

Menambahkan fitur **cetak formulir penerimaan tamu** di modul Visitor. Alur (skenario):

1. Tamu datang → security membuka menu **Visitor** → klik **Record Visitor Entry** → isi form → simpan (`save()`).
2. Setelah tersimpan, security **langsung diarahkan ke halaman print formulir** tamu tersebut (halaman ini sekaligus jadi **print preview**).
3. Security klik tombol **Print** → dialog print browser muncul → formulir dicetak di kertas kecil.
4. Formulir juga bisa **dicetak ulang** kapan saja dari tabel daftar tamu (tombol printer per baris) dan dari modal **View**.

**Catatan penting:** Tanggal & Waktu **keluar** TIDAK dicetak di formulir (belum ada saat tamu masuk). Itu tetap dicatat lewat sistem (fitur Record Exit yang sudah ada).

---

## Isi Formulir (attribute yang dicetak)

Semua data diambil **otomatis** dari record `Visitor`:

| No | Label di formulir | Sumber data |
|---|---|---|
| 1 | Nama Lengkap | `$visitor->name` |
| 2 | Asal (Perusahaan / Instansi) | `$visitor->company` (fallback `-`) |
| 3 | Orang yang Dituju & Bagian | `$visitor->visiting` + **bagian** (lihat catatan di bawah) |
| 4 | Keperluan / Tujuan | `$visitor->purpose` |
| 5 | Tanggal & Waktu Masuk | `$visitor->formatted_date` + `$visitor->entry_time` |
| 6 | No. Kartu Visitor | `$visitor->card_number` (fallback `-`) |
| 7 | Tanggal Surat Dibuat | `$visitor->created_at->format('d/m/Y')` |

Di bawahnya: **2 kolom tanda tangan sejajar horizontal**
- Kiri: **Tanda tangan Tamu** → di bawah garis tanda tangan tulis nama `{{ $visitor->name }}`.
- Kanan: **Tanda tangan Orang yang Dituju** → di bawah garis tanda tangan tulis nama `{{ $visitor->visiting }}`.

### ⚠️ Catatan soal "Bagian" (perlu konfirmasi user saat review)

Saat ini kolom `visiting` di tabel `visitors` **hanya menyimpan nama** orang yang dituju (string), **tidak menyimpan bagian/section/department**-nya. Autocomplete di form entry sebenarnya sudah menerima data `department` & `section` dari API `/employees/api`, tapi tidak disimpan.

Ada 2 opsi (implementor pilih **Opsi A** kecuali user minta lain saat review):

- **Opsi A (Direkomendasikan) — simpan "bagian" saat entry.**
  Tambah kolom `visiting_section` (nullable string) ke tabel `visitors`, isi otomatis dari pilihan autocomplete. Dengan begitu "Bagian" benar-benar otomatis di formulir. Langkah detail ada di **Task 0** di bawah.
- **Opsi B (Minimal) — biarkan bagian kosong.**
  Skip Task 0. Di formulir, baris "Orang yang Dituju" cukup tampilkan nama, dan sediakan tempat kosong bergaris untuk diisi tangan. Pilih ini hanya kalau user setuju bagian tidak otomatis.

---

## Task 0 — (Opsi A) Simpan bagian orang yang dituju

> Skip task ini jika user memilih Opsi B.

1. Buat migration:
   ```
   php artisan make:migration add_visiting_section_to_visitors_table --no-interaction
   ```
   Isi `up()`: `$table->string('visiting_section')->nullable()->after('visiting');`
   Isi `down()`: `$table->dropColumn('visiting_section');`
   Jalankan `php artisan migrate`.

2. `⚡visitor.blade.php`:
   - Tambah property `public $visiting_section;`
   - Di `save()`, tambahkan `'visiting_section' => $this->visiting_section,` ke `Visitor::create([...])`.
   - Di blok `<script>` autocomplete (event `selection`), setelah `$wire.set('visiting', selection.fullname);` tambahkan:
     ```js
     $wire.set('visiting_section', selection.section?.name ?? selection.department?.name ?? null);
     ```
     (API mengembalikan relasi `section` dan `department`; lihat route `/employees/api` di `routes/web.php`.)

3. `⚡visitor-manual-entry.blade.php`: kolom ini opsional untuk manual entry (boleh dibiarkan `null` karena bisa diketik bebas). Tidak wajib diubah.

4. Di formulir print, baris "Orang yang Dituju & Bagian" tampilkan:
   `{{ $visitor->visiting }}` dan di bawah/di sampingnya `{{ $visitor->visiting_section ?: '-' }}`.

---

## Task 1 — Route & view halaman print

Pola print yang dipakai: **halaman HTML mandiri (standalone)**, BUKAN Volt/Livewire dan BUKAN memakai layout dashboard. Alasannya: supaya kertas bersih tanpa sidebar/menu, dan CSS print (`@page`, ukuran kertas kecil) tidak bentrok dengan Flux/Tailwind dashboard.

### 1a. Route
Di `routes/web.php`, di dalam group `Route::middleware(['auth'])`, tambahkan (letakkan dekat route visitor lain):

```php
Route::get('dashboard/visitor/{visitor}/print', function (\App\Models\Visitor $visitor) {
    return view('pages.dashboard.visitor-print', ['visitor' => $visitor]);
})->name('dashboard.visitor.print');
```

> `{visitor}` memakai route-model-binding, jadi otomatis 404 kalau id tidak ada. Tetap di dalam middleware `auth` supaya hanya user login yang bisa cetak.

### 1b. View print
Buat file **baru**: `resources/views/pages/dashboard/visitor-print.blade.php`.

Ini view HTML lengkap (punya `<html>`, `<head>`, `<body>` sendiri). Ukuran kertas **A6** (= A4 dibagi 4, kira-kira 105mm × 148mm). Gunakan CSS `@page` untuk set ukuran & margin, dan `@media print` untuk menyembunyikan tombol saat dicetak.

Gunakan kode berikut sebagai dasar (silakan rapikan, tapi jaga strukturnya):

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Formulir Penerimaan Tamu - {{ $visitor->name }}</title>
    <style>
        @page {
            size: A6;            /* A4 dibagi 4 */
            margin: 8mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            color: #000;
            margin: 0;
            padding: 10px;
        }

        .sheet {
            width: 105mm;
            margin: 0 auto;
        }

        .title {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .subtitle {
            text-align: center;
            font-size: 8pt;
            margin-bottom: 8px;
        }

        hr { border: none; border-top: 1px solid #000; margin: 6px 0; }

        table.fields { width: 100%; border-collapse: collapse; }
        table.fields td { vertical-align: top; padding: 2px 0; }
        table.fields td.label { width: 42%; }
        table.fields td.sep { width: 4%; }
        table.fields td.value { width: 54%; font-weight: bold; }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 18px;
            text-align: center;
        }
        .signatures .sign-box { width: 48%; }
        .sign-line {
            margin-top: 40px;
            border-top: 1px solid #000;
            padding-top: 2px;
            font-weight: bold;
        }

        .actions { text-align: center; margin-top: 16px; }
        .actions button {
            padding: 6px 16px;
            font-size: 10pt;
            cursor: pointer;
        }

        /* Saat mencetak, sembunyikan tombol */
        @media print {
            .actions { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="title">Formulir Penerimaan Tamu</div>
        <div class="subtitle">PT Ewindo</div>
        <hr>

        <table class="fields">
            <tr>
                <td class="label">Nama Lengkap</td>
                <td class="sep">:</td>
                <td class="value">{{ $visitor->name }}</td>
            </tr>
            <tr>
                <td class="label">Asal (Perusahaan / Instansi)</td>
                <td class="sep">:</td>
                <td class="value">{{ $visitor->company ?: '-' }}</td>
            </tr>
            <tr>
                <td class="label">Orang yang Dituju</td>
                <td class="sep">:</td>
                <td class="value">{{ $visitor->visiting }}</td>
            </tr>
            <tr>
                <td class="label">Bagian</td>
                <td class="sep">:</td>
                <td class="value">{{ $visitor->visiting_section ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Keperluan / Tujuan</td>
                <td class="sep">:</td>
                <td class="value">{{ $visitor->purpose }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal &amp; Waktu Masuk</td>
                <td class="sep">:</td>
                <td class="value">{{ $visitor->formatted_date }} {{ $visitor->entry_time }}</td>
            </tr>
            <tr>
                <td class="label">No. Kartu Visitor</td>
                <td class="sep">:</td>
                <td class="value">{{ $visitor->card_number ?: '-' }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal Surat Dibuat</td>
                <td class="sep">:</td>
                <td class="value">{{ $visitor->created_at->format('d/m/Y') }}</td>
            </tr>
        </table>

        <div class="signatures">
            <div class="sign-box">
                Tanda Tangan Tamu
                <div class="sign-line">{{ $visitor->name }}</div>
            </div>
            <div class="sign-box">
                Orang yang Dituju
                <div class="sign-line">{{ $visitor->visiting }}</div>
            </div>
        </div>

        <div class="actions">
            <button onclick="window.print()">Print</button>
            <button onclick="window.close()">Tutup</button>
        </div>
    </div>
</body>
</html>
```

> Catatan: kalau Opsi B (tanpa Task 0) dipilih, ganti baris `Bagian` value menjadi garis kosong untuk diisi tangan (mis. `<td class="value">&nbsp;</td>`), atau hapus barisnya sesuai keinginan user.

> **Jangan** auto-`window.print()` saat `onload` — biarkan user menekan tombol Print (ini yang dimaksud "print preview": halaman tampil dulu sebagai preview, baru user cetak). Dialog print browser sendiri sudah punya preview.

---

## Task 2 — Arahkan ke halaman print setelah simpan entry

Di `⚡visitor.blade.php`, ubah method `save()` supaya setelah tamu tersimpan, halaman print dibuka **di tab baru** (supaya daftar tamu tetap terbuka).

1. Tangkap hasil create:
   ```php
   $visitor = Visitor::create([...]);   // ganti dari Visitor::create([...]) tanpa assign
   ```
2. Setelah `$this->reset();` dan `Flux::modal('record-visitor')->close();`, tambahkan:
   ```php
   $this->dispatch('open-print', url: route('dashboard.visitor.print', $visitor));
   ```
   > **Hati-hati urutan:** panggil `route(...)` untuk `$visitor` **sebelum** `$this->reset()` kalau `reset()` sampai menghapus `$visitor` — di sini `$visitor` variabel lokal jadi aman, tapi pastikan `$visitor->id` sudah ada (setelah `create`).
3. Di blok `<script>` di bagian bawah file (yang sudah ada untuk autocomplete), tambahkan listener:
   ```js
   window.addEventListener('open-print', (event) => {
       window.open(event.detail.url, '_blank');
   });
   ```
   > Browser bisa memblokir popup. Karena ini dipicu oleh aksi user (submit form), umumnya diizinkan. Kalau terblokir, user tetap bisa print via tombol printer di tabel (Task 3).

---

## Task 3 — Tombol print ulang (tabel & modal View)

Supaya formulir bisa dicetak ulang kapan saja:

1. **Di tabel daftar tamu** (`⚡visitor.blade.php`), pada kolom action (sebelah tombol **View**), tambahkan tombol printer yang membuka route print di tab baru:
   ```blade
   <flux:button
       icon="printer"
       variant="ghost"
       size="sm"
       href="{{ route('dashboard.visitor.print', $visitor) }}"
       target="_blank">
       Print
   </flux:button>
   ```
   > Gunakan `href` + `target="_blank"` (link biasa), **jangan** `wire:navigate`, karena ini halaman non-Livewire.

2. **Di modal `view-visitor`**, tambahkan tombol print di bagian bawah (mis. sebelum/sesudah "Record Information"):
   ```blade
   <flux:button
       icon="printer"
       variant="primary"
       href="{{ route('dashboard.visitor.print', $this->visitor) }}"
       target="_blank">
       Print Formulir
   </flux:button>
   ```

---

## Test

Buat `tests/Feature/Dashboard/VisitorPrintTest.php` (`php artisan make:test --pest Dashboard/VisitorPrintTest`):

1. **Halaman print tampil & berisi data tamu:**
   - Login sebagai user, buat `Visitor` (pakai factory kalau ada; kalau belum ada, buat via `Visitor::create([...])`).
   - `get(route('dashboard.visitor.print', $visitor))` → `assertOk()`.
   - `assertSee($visitor->name)`, `assertSee($visitor->company)`, `assertSee($visitor->visiting)`, `assertSee($visitor->card_number)`, dan assert tidak menampilkan waktu keluar (mis. tidak ada label "Waktu Keluar"/"Exit").
2. **Butuh auth:** tanpa login → `get(...)` redirect ke login (`assertRedirect`).
3. **(Jika Task 0 dikerjakan)** Test bahwa `save()` menyimpan `visiting_section`, dan halaman print menampilkannya. Untuk komponen Volt gunakan `Livewire::test('pages.dashboard.visitor', ...)` atau `Volt::test(...)` sesuai konvensi test lain di `tests/Feature/Dashboard/` (lihat `VisitorExitTest.php` sebagai contoh pola test halaman visitor).

> Cek `tests/Feature/Dashboard/VisitorExitTest.php` untuk pola login & pembuatan data yang sudah dipakai di modul ini, dan ikuti gayanya.

---

## Checklist sebelum selesai
- [ ] Route `dashboard.visitor.print` ada & di dalam middleware `auth`.
- [ ] View `visitor-print.blade.php` tampil rapi di kertas A6 (test manual: buka halaman, Ctrl+P, cek preview ukuran & layout 2 tanda tangan sejajar).
- [ ] Setelah `save()` entry baru, tab print terbuka otomatis.
- [ ] Tombol print ulang berfungsi di tabel & modal View.
- [ ] Waktu/tanggal **keluar** TIDAK muncul di formulir.
- [ ] `vendor/bin/pint --dirty --format agent` bersih.
- [ ] `php artisan test --compact --filter=VisitorPrint` lulus.
- [ ] Kalau perubahan blade tidak muncul, ingatkan user jalankan `npm run build` / `npm run dev`.

## Catatan
- Jangan mengubah dependency tanpa approval (lihat `AGENTS.md`).
- Ukuran kertas final (A6) bisa disesuaikan user setelah lihat hasil cetak fisik — parameter ada di `@page { size: ... }` dan `.sheet { width: ... }`.
