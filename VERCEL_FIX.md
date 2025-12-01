# 🔧 Fix Vercel Deployment Error

## Masalah
Error: "The Runtime "vercel-php@0.6.0" is using "nodejs18.x", which is discontinued."

## Solusi yang Sudah Dicoba

### 1. Update vercel.json
- Menghapus spesifikasi runtime yang outdated
- Menggunakan `@vercel/php` builder
- Menambahkan `package.json` dengan Node.js 20.x

### 2. File yang Diperbarui
- `vercel.json` - Konfigurasi routing tanpa runtime spesifik
- `package.json` - Menentukan Node.js 20.x

## Alternatif Solusi

Jika masih error, coba salah satu dari berikut:

### Opsi 1: Gunakan Runtime Tanpa Spesifikasi
```json
{
  "version": 2,
  "routes": [
    { "src": "/(.*)", "dest": "api/index.php" }
  ]
}
```

### Opsi 2: Install @vercel/php Package
```bash
npm install @vercel/php
```

Kemudian update `vercel.json`:
```json
{
  "version": 2,
  "builds": [
    {
      "src": "api/index.php",
      "use": "@vercel/php"
    }
  ],
  "routes": [
    { "src": "/(.*)", "dest": "api/index.php" }
  ]
}
```

### Opsi 3: Gunakan Vercel CLI untuk Auto-detect
Hapus `vercel.json` dan biarkan Vercel auto-detect PHP files.

### Opsi 4: Migrate ke Platform Lain
Jika Vercel tidak support PHP dengan baik, pertimbangkan:
- **Railway** - Support PHP native
- **Render** - Support PHP dengan mudah
- **Heroku** - Support PHP (paid)
- **DigitalOcean App Platform** - Support PHP

## Testing

Setelah update, commit dan push:
```bash
git add vercel.json package.json
git commit -m "Fix Vercel runtime error"
git push
```

Kemudian redeploy di Vercel dashboard.

