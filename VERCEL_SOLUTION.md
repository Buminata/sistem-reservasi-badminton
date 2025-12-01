# 🔧 Solusi Error Vercel Runtime

## Error
"The Runtime "vercel-php@0.6.0" is using "nodejs18.x", which is discontinued."

## Solusi yang Sudah Diterapkan

1. ✅ Menghapus spesifikasi runtime dari `vercel.json`
2. ✅ Menggunakan `rewrites` instead of `routes`
3. ✅ Menghapus `version: 2` (menggunakan default)
4. ✅ Membuat `package.json` dengan Node.js 20.x

## Konfigurasi Saat Ini

### vercel.json
```json
{
  "rewrites": [
    { "source": "/(.*)", "destination": "/api/index.php" }
  ]
}
```

### package.json
```json
{
  "engines": {
    "node": "20.x"
  }
}
```

## Langkah Selanjutnya

### 1. Clear Vercel Cache
Di Vercel Dashboard:
- Settings → General → Clear Build Cache
- Atau buat deployment baru dengan "Redeploy"

### 2. Jika Masih Error
Coba salah satu solusi berikut:

#### Opsi A: Hapus vercel.json Sementara
Hapus file `vercel.json` dan biarkan Vercel auto-detect PHP files.

#### Opsi B: Gunakan Konfigurasi Manual di Vercel Dashboard
1. Masuk ke Vercel Dashboard
2. Settings → General
3. Framework Preset: Pilih "Other"
4. Root Directory: Biarkan kosong atau set ke root
5. Build Command: Kosongkan
6. Output Directory: Kosongkan
7. Install Command: Kosongkan

#### Opsi C: Pertimbangkan Platform Alternatif
Jika Vercel masih bermasalah dengan PHP:

**Railway** (Recommended untuk PHP):
- Support PHP native
- Database included
- File storage support
- Easy deployment dari GitHub

**Render**:
- Support PHP dengan mudah
- Free tier available
- Database support

**DigitalOcean App Platform**:
- Support PHP
- Managed database
- File storage

## Testing

Setelah update, commit dan push:
```bash
git add vercel.json package.json
git commit -m "Remove runtime specification for Vercel"
git push
```

Kemudian redeploy di Vercel Dashboard.

