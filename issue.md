Buatkan issue di github yang menjelaskan langkah implementasi secara detail untuk junior programmer atau AI model yang lebih murah.

Saya sudah memiliki satu menu Request namun masih belum implementasi karena harus menunggu data dari aplikasi lain. Sekarang aplikasi itu sudah launch dan aplikasi ini sudah siap menerima data yang dihasilkan dari aplikasi tersebut. Fitur Request ini berisi list semua karyawan yang melakukan cuti ataupun izin (karena ketika meninggalkan fasilitas harus tercatat).

Jadi fitur ini tidak diinput oleh security melainkan hanya record waktu keluar dan waktu masuk kembali (jika karyawan kembali ke fasilitas). Tabel sudah otomatis terisi harian sesuai karyawan yang melakukan cuti atau izin.

Field nya antara lain Employee, Department, Leave type, Date, Permitted Time (rentang waktu cuti / izin), Actual Time (Button untuk record waktu keluar), Actual Return (Tombol muncul ketika Tombol Actual Time sudah di klik, atau tombol tidak muncul jika karyawan tidak kembali ke fasilitas), dan icon Action untuk lihat detail.

Untuk Employee dan Department sudah dilakukan fetch data dari database Superapp, sekarang untuk transaksi cutinya akan mengambil dari database yang sama dengan skema "pms". Tabel yang dibutuhkan hanya p_requests dan p_approvements. Silahkan pelajari dan analisa.

⚠️ ATURAN DB — WAJIB, TIDAK BISA DITAWAR. JANGAN PERNAH menjalankan php artisan test, migrate:fresh, migrate:refresh, db:wipe, atau db:seed terutama database Superapp (secondary database untuk fetch data). Kalau kamu merasa butuh migrasi di database primary; berhenti dan tanya reviewer.