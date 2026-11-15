# PRD 1.0: Sistem Pembuatan Resi Hadjar Express

| | |
|---|---|
| Versi | 1.0 (revisi, 13 November 2026) |
| Status | Draft untuk persetujuan |
| Pemilik produk | PT Hadjar Syaqira Cargo |
| Pengembang | Ell |
| Stack | Laravel 13, PHP 8.4, Filament 5, MariaDB, Redis, Nginx di VPS Hostinger KVM 2 |

---

## 1. Ringkasan

Sistem ini dipakai dua jenis pengguna lewat satu aplikasi:
- **Mitra**: membuat order pengiriman, claim resi, dan mengelola daftar penerimanya.
- **Super Admin**: mengatur akun, tarif, saldo, inbound, dan konfirmasi vendor. Super Admin juga bisa membuat order.

Halaman publik hanya berisi kolom lacak resi dan tombol Login.

Desain Choir Frame hanya dipakai sebagai acuan tata letak. Nama, logo, dan warna mengikuti merek Hadjar Express. Logo HSC dipakai di panel dan label.

## 2. Teknologi

| Bagian | Teknologi |
|---|---|
| Panel mitra dan Super Admin | Filament 5 (sidebar, tabel, form, filter, dan modal) |
| Halaman publik lacak dan login | Blade sederhana, dengan Tailwind CSS |
| Backend | Laravel 13 (satu repositori dengan Filament) |
| Autentikasi | Sesi Laravel bawaan, dengan peran `mitra` dan `super_admin` |
| Database | MariaDB |
| Antrean dan cache | Redis |
| Verifikasi bot | Cloudflare Turnstile |
| Barcode dan QR | `picqer/php-barcode-generator` dan `bacon/bacon-qr-code` |
| Label cetak | Blade khusus cetak dengan `@media print` (thermal 100 × 150 mm dan A4) |
| Web server | Nginx dan PHP-FPM, SSL dari Cloudflare |

Tidak ada framework JavaScript terpisah. React tidak dipakai.

## 3. Peran dan akun

- **Super Admin**: akun pertama dibuat lewat perintah artisan. Kode mitranya `HSC 001`.
- **Mitra**: dibuat oleh Super Admin setelah verifikasi WhatsApp. Kode mitra 3 digit diberikan otomatis saat akun dibuat, dimulai dari `002`.
- Saat dibuat, sistem menampilkan kata sandi sementara sekali. Mitra wajib mengganti kata sandi saat login pertama.
- Login dikunci 15 menit setelah 5 kali gagal. Sesi berakhir setelah 2 jam tidak aktif.
- Akun nonaktif tidak bisa login.

### 3.1 Akses

| Aksi | Mitra | Super Admin |
|---|---|---|
| Buat order | Ya | Ya |
| Claim resi | Ya | Ya |
| Isi data Inbound (Tambah data, opsional) | Ya | Ya |
| Isi foto dan berat di Inbound | Ya | Ya |
| Scan atau ketik nomor di Outbound (opsional) | Ya | Ya |
| Konfirmasi resi dan pilih vendor | Tidak | Ya |
| Kelola akun mitra dan paket | Tidak | Ya |
| Kelola tarif, negara, dan barang dilarang | Tidak | Ya |
| Tambah dan kurangi saldo mitra | Tidak | Ya |
| Lihat saldo dan mutasi sendiri | Ya | Semua mitra |
| Lihat semua order | Hanya miliknya | Ya |
| Lihat daftar penerima | Hanya miliknya | Semua, dengan audit |

## 4. Format nomor resi

Format: `HSC` + kode mitra (3 digit) + tahun (2 digit) + tanggal (2 digit) + bulan (2 digit) + urutan (3 digit).

Contoh untuk zan001 (kode `001`), 13 November 2026, resi pertama: **`HSC 001 26 13 11 001`**.

- Urutan dihitung **per mitra**.
- Urutan **tidak direset**, kecuali sudah mencapai `999`.
- Nomor internal dibuat sekali saat claim. Tidak pernah diubah atau dipakai ulang.

## 5. Alur utama

```
Mitra / Super Admin
  └─ Order Shipment: isi form pengiriman ─► Draft (form belum lengkap)
                                         ─► Belum selesai (form lengkap, belum di-claim)
                                              │
Inbound (mitra / Super Admin)                 ▼
  └─ Tambah data (opsional)              Claim jadi resi / Claim multiple
     foto 1 wajib, foto 2–3 opsional         │  saldo dipotong, nomor HSC dibuat,
                                             │  label pertama dicetak
                                             ▼
                                        Selesai (sudah claim, menunggu konfirmasi)
                                             │
Super Admin: validasi berat, dimensi,        ▼
foto, pilih vendor, AWB terbit          Outbound (sudah dikonfirmasi Super Admin)
                                             │
Outbound Scan (opsional, mitra & SA)         ▼
  └─ scan label atau ketik nomor       tercatat keluar cabang
```

### 5.1 Arti status di kartu ringkasan Order Shipment

| Kartu | Arti |
|---|---|
| **Draft** | Form belum selesai diisi |
| **Belum selesai** | Form sudah lengkap, tapi resi belum di-claim dan saldo belum dipotong |
| **Selesai** | Sudah di-claim dan saldo sudah dipotong. Belum dikonfirmasi Super Admin |
| **Outbound** | Sudah dikonfirmasi Super Admin: validasi selesai, vendor dipilih, AWB terbit |

Status internal yang dipakai: `draft`, `antrean`, `resi_terbit`, `tervalidasi`, `dibatalkan`. Status vendor: `belum_dipilih`, `menerbitkan`, `terbit`, `gagal`. Status fisik: `menunggu_inbound`, `inbound`, `outbound`.

## 6. Order Shipment

### 6.1 Halaman daftar
- Judul "Order Shipment" dan tombol **+ Buat order** di kanan atas.
- Empat kartu ringkasan: Draft, Belum selesai, Selesai, Outbound, beserta jumlahnya.
- Filter: pencarian (nomor resi, nama penerima), dari dan sampai tanggal, negara tujuan, rentang cepat (hari ini, 7 hari, 30 hari), tombol Reset dan Terapkan.
- Tabel: aksi, nomor resi, tanggal, status, penerima, negara tujuan, berat, dan layanan.
- Paginasi 10 baris per halaman.

### 6.2 Form pengiriman
Form memanjang ke bawah, satu kolom, dengan urutan:

1. **Pengirim**: nama, nomor HP, alamat. Diambil dari profil. Boleh diubah.
2. **Penerima**: nama, telepon, negara tujuan (wajib), kota, kode pos, alamat, nomor ARC (wajib untuk negara wajib ARC), foto ARC depan dan belakang (wajib untuk negara wajib ARC).
   - Persetujuan penyimpanan data identitas wajib dicentang.
   - Penerima yang pernah dikirimi tersimpan otomatis di daftar penerima mitra. Saat nama atau telepon diketik, data muncul untuk dipilih dan terisi otomatis.
3. **Informasi paket**: jenis paket, layanan, berat aktual (kg), panjang, lebar, tinggi (cm). Berat volume dihitung otomatis: P × L × T ÷ 5.000.
4. **Detail barang**: jenis barang, nama barang, jumlah, nilai per barang (USD). Bisa lebih dari satu. Total nilai dihitung otomatis dalam USD.
5. **Catatan**: opsional.
6. **Pernyataan**: wajib dicentang bahwa paket tidak berisi barang dilarang.
7. Tombol **Simpan**.

Tombol simpan tidak aktif sampai semua kolom wajib terisi.

### 6.3 Aturan form
- Negara wajib ARC: nomor ARC minimal 8 karakter, dua foto JPG atau PNG maksimal 5 MB per foto.
- Negara lain: nomor identitas opsional.
- Form yang sudah di-claim tidak bisa diubah.

## 7. Inbound

### 7.1 Halaman daftar
- Judul "Inbound" dan tombol: **Tambah data**, **Batalkan**, **Claim jadi resi**, **Claim multiple resi**.
- Filter: cari (kode, pengirim, isi paket), rentang tanggal, dan rentang cepat.
- Tips di bagian atas: pilih baris dengan centang, atau klik dua kali baris yang masih bisa di-claim.
- Tabel: centang, kode, foto, status, tanggal, pengirim, telepon, berat, isi paket.

### 7.2 Tambah data (opsional)
Dipakai untuk paket yang masuk tanpa order sebelumnya. Modal "Input paket masuk" berisi:
- **Nama pengirim** (wajib), **telepon pengirim** (wajib)
- **Berat (kg)** (wajib), **isi paket** (wajib)
- **Foto paket 1** (wajib), **foto paket 2** dan **foto paket 3** (opsional)
- **Data penerima** (opsional di sini): mitra boleh melengkapinya, boleh juga melengkapi lewat form Order nanti.

Tombol Simpan dan Batal.

### 7.3 Claim
- Hanya resi dengan **data penerima lengkap** yang bisa di-claim. Penerima dilengkapi di Tambah data atau di form Order.
- Claim satu resi, atau claim multiple resi sekaligus. Hasil ditampilkan per baris. Yang gagal tidak membatalkan yang berhasil.
- Saat claim, sistem:
  1. menghitung harga dari berat chargeable (lebih besar antara berat aktual dan berat volume),
  2. memeriksa saldo. Claim ditolak jika saldo kurang, dengan pesan saldo, harga, dan kekurangannya,
  3. memotong saldo dalam satu transaksi,
  4. membuat nomor HSC sesuai bagian 4,
  5. mengunci harga dan data sebagai snapshot,
  6. mencetak label pertama, status menjadi `resi_terbit`.

### 7.4 Pembatalan
- Mitra bisa membatalkan resi yang belum di-claim tanpa dampak saldo.
- Mitra bisa membatalkan resi yang sudah di-claim sebelum konfirmasi Super Admin. Saldo dikembalikan.
- Setelah konfirmasi, hanya Super Admin yang bisa membatalkan, dengan alasan.

## 8. Konfirmasi Super Admin

Di halaman resi berstatus "Selesai", Super Admin:
1. memeriksa berat aktual dan dimensi. Jika berbeda dari form, harga dihitung ulang dan selisihnya dicatat sebagai mutasi "koreksi validasi",
2. memeriksa foto paket,
3. memilih vendor: CEX, Goorita, Aramex, atau Rayspeed,
4. untuk CEX dan Goorita, sistem membuat AWB lewat API. Untuk Aramex dan Rayspeed, Super Admin mengisi AWB manual,
5. label final dicetak, dengan AWB vendor di atas dan nomor HSC di bawah,
6. resi berpindah ke kartu **Outbound**.

Aturan:
- Satu vendor per resi. Vendor tidak bisa diganti setelah AWB terbit.
- Jika salah pilih vendor, resi dibatalkan dan dibuat ulang, sebelum outbound scan.
- AWB unik. Jika vendor membalas AWB yang sudah dipakai, resi ditandai untuk diperiksa, bukan dibuang diam-diam.
- Panggilan ke vendor berjalan lewat antrean Redis. Goorita tidak diulang otomatis.

## 9. Outbound Scan (opsional)

- Menu untuk mitra dan Super Admin.
- Pemindaian label atau mengetik nomor resi, lalu tekan Proses.
- Hasilnya mencatat resi sebagai **tercatat keluar cabang** dengan waktu dan nama pemindai.
- Hanya bisa dipakai untuk resi yang sudah berstatus Outbound.
- Nomor yang sudah tercatat tidak bisa dipindai dua kali.
- Scan bersifat opsional. Tidak wajib untuk setiap paket.

## 10. Cek Harga

- Mitra dan Super Admin memasukkan negara, berat, dan dimensi.
- Sistem menampilkan berat volume, berat chargeable, tier, dan harga sesuai paket mitra.
- Cek harga tidak membuat resi dan tidak mengubah saldo.

## 11. Tarif (Super Admin)

- Tarif disusun per **paket × negara × rentang berat**.
- Contoh Taiwan:
  - 1 sampai 5 kg: Rp80.000 per kg
  - 6 sampai 10 kg: Rp70.000 per kg
- Harga dihitung dengan seluruh berat dikali harga per kg tier tempat berat itu jatuh. Contoh: 6 kg × Rp70.000 = Rp420.000.
- Tidak boleh ada rentang yang tumpang tindih untuk paket, negara, dan masa berlaku yang sama.
- Setiap perubahan tarif tercatat di audit log. Resi yang sudah di-claim tidak berubah.

## 12. Saldo

- Hanya Super Admin yang bisa menambah atau mengurangi saldo, dengan alasan dan nomor referensi transfer.
- Saldo tidak boleh minus.
- Setiap perubahan tercatat sebagai mutasi dan tidak bisa diedit.
- Jenis mutasi: tambah, kurang, potong claim, koreksi validasi, kembali.

## 13. Lacak resi publik

- Halaman depan: navbar hanya berisi logo HSC di kiri dan tombol **Login** di pojok kanan atas.
- Di tengah halaman: kolom nomor resi dan tombol Lacak.
- Verifikasi bot dengan Cloudflare Turnstile, diperiksa di server.
- Bisa memasukkan beberapa nomor (maksimal 10), satu per baris.
- Hasil menampilkan nomor, status, keterangan terakhir, dan jejak perjalanan.
- Tidak menampilkan nama, alamat, telepon, nomor ARC, foto, atau nama vendor.
- Nomor yang tidak ada dan nomor yang belum dikonfirmasi menampilkan pesan yang sama.
- Batas 30 permintaan per menit per IP. Hasil di-cache 3 menit.

## 14. Daftar penerima

- Setiap mitra punya daftar penerimanya sendiri. Mitra lain tidak bisa melihatnya.
- Mitra bisa mencari, melihat, mengubah, mengganti foto ARC, dan menghapus penerima.
- Menghapus penerima hanya menghapus profilnya. Resi yang sudah di-claim tetap menyimpan salinan data penerima.
- Super Admin bisa melihat daftar semua mitra. Setiap akses dicatat.
- Permintaan hapus dari penerima diteruskan lewat mitra pengirim.

## 15. Data pribadi dan penyimpanan

- Nomor ARC dan data identitas penerima dienkripsi.
- Foto ARC dan foto paket disimpan di penyimpanan privat, dibuka lewat tautan sementara.
- Foto ARC pada resi dihapus otomatis **1 tahun** setelah paket terkirim. Penghapusan dicatat.
- Versi lama foto ARC disimpan dan hanya bisa dilihat Super Admin.
- Cadangan database harian disimpan **30 hari**, dengan salinan di luar VPS. Uji restore minimal sekali sebelum go-live.

## 16. Audit minimal

Aksi berikut dicatat dengan pelaku dan waktu: perubahan saldo, claim, validasi, pilih vendor, pembatalan, outbound, penghapusan penerima, akses daftar penerima oleh Super Admin, dan perubahan tarif. Isi data pribadi tidak masuk log.

## 17. Yang tidak termasuk

- Impor CSV harga (diisi manual dulu).
- Laporan pengiriman, infografik, dan ekspor CSV.
- Pengajuan API untuk mitra.
- Top-up yang diajukan mitra.
- Kurs USD ke IDR.
- Pembayaran online, aplikasi mobile, dan notifikasi WhatsApp otomatis.
- Kalkulator dan promo website lama.
- Dashboard di luar kartu ringkasan Order Shipment.

## 18. Keamanan dan kinerja

- HTTPS dengan Cloudflare SSL Full (strict).
- Setiap aksi memeriksa peran di server, bukan hanya menyembunyikan menu.
- Rahasia hanya di `.env` (izin 600), tidak masuk git, dan tidak dibagikan di chat.
- Halaman lacak publik memuat di bawah 2 detik.
- Panel bisa dipakai di HP.
- Zona waktu Asia/Jakarta. Bahasa Indonesia.

## 19. Item yang belum dikonfirmasi

| ID | Pertanyaan | Usulan |
|---|---|---|
| T2 | Nama domain | Menunggu keputusan |
| T6 | Nama vendor di lacak publik | Tidak ditampilkan |
| T10 | Negara tujuan CEX di production | Konfirmasi ke CEX |
| T26 | Pembulatan harga | Ke rupiah terdekat |
| T27 | Batas resi tertahan saat menunggu vendor | Ditetapkan Super Admin |
| T28 | Pembatalan setelah claim, sebelum konfirmasi | Boleh, saldo dikembalikan |
| R1 | Urutan setelah 999 | Kembali ke 001. Perlu dicek agar tidak bentrok dengan tanggal yang sama |
| R2 | Penerima di Tambah data | Opsional. Claim tetap menunggu penerima lengkap |

## 20. Urutan pembangunan

| Fase | Isi | Selesai jika |
|---|---|---|
| 0. Fondasi | Laravel 13 dengan Filament 5, peran `mitra` dan `super_admin`, login, logo HSC, tema warna | Login berhasil, peran terbaca, tombol akun sesuai peran |
| 1. Akun | Buat akun mitra oleh Super Admin dengan kode otomatis, ganti kata sandi pertama, kunci login | Mitra tidak bisa membuka menu Super Admin |
| 2. Paket, negara, tarif | Paket, negara dengan penanda wajib ARC, tarif per kg, cek harga | Tarif tumpang tindih ditolak. Cek harga sesuai paket |
| 3. Saldo | Tambah dan kurang, riwayat mutasi, larangan minus | Saldo sama dengan jumlah mutasi |
| 4. Order Shipment | Kartu ringkasan, filter, tabel, form satu kolom, ARC, daftar penerima otomatis | Tombol simpan tidak aktif sampai field wajib lengkap |
| 5. Inbound dan claim | Tambah data (opsional), claim satu dan banyak, nomor HSC, label pertama | Saldo kurang menolak claim. Potong tepat sekali |
| 6. Konfirmasi | Validasi berat dan foto, pilih vendor, AWB, label final, pindah ke Outbound | Tidak ada AWB ganda. Satu vendor per resi |
| 7. Outbound Scan | Scan atau ketik nomor, tercatat keluar cabang | Nomor tidak bisa dicatat dua kali |
| 8. Lacak | Lacak internal, halaman publik dengan Turnstile | Respons publik tanpa data pribadi |
| 9. Pelengkap | Audit minimal, penghapusan foto ARC otomatis, cadangan harian | Restore cadangan berhasil |

## 21. Persetujuan

| Peran | Nama | Tanggal | Keputusan |
|---|---|---|---|
| Pemilik produk | | | |
| Pengembang | Ell | | |
