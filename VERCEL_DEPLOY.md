# 🚀 Panduan Deploy ke Vercel

## File Konfigurasi

1. **vercel.json** - Konfigurasi routing Vercel
2. **api/index.php** - Entry point untuk semua request

## Cara Deploy

### Method 1: Via Vercel CLI

```bash
# Install Vercel CLI
npm i -g vercel

# Login ke Vercel
vercel login

# Deploy
vercel

# Deploy ke production
vercel --prod
```

### Method 2: Via GitHub Integration

1. Push code ke GitHub (sudah dilakukan)
2. Login ke [Vercel](https://vercel.com)
3. Import project dari GitHub
4. Vercel akan otomatis detect `vercel.json`
5. Deploy!

## Environment Variables

Set environment variables di Vercel Dashboard:

```
DB_HOST=your-database-host
DB_NAME=your-database-name
DB_USER=your-database-user
DB_PASS=your-database-password
```

## Catatan Penting

⚠️ **Database**: Vercel tidak menyediakan MySQL. Anda perlu:
- Menggunakan database external (PlanetScale, Railway, dll)
- Atau menggunakan Vercel Postgres
- Update koneksi database di `api/db.php`

⚠️ **File Uploads**: 
- Vercel serverless functions tidak menyimpan file secara permanen
- Gunakan cloud storage (AWS S3, Cloudinary, dll) untuk upload file
- Update path upload di `api/payment.php` dan `api/membership.php`

⚠️ **Session Storage**:
- Vercel menggunakan serverless functions
- Session mungkin tidak persist antar request
- Pertimbangkan menggunakan JWT atau database session storage

## Testing

Setelah deploy, test endpoint:
- `https://your-app.vercel.app/` - Homepage
- `https://your-app.vercel.app/api/login.php` - API endpoint
- `https://your-app.vercel.app/login.html` - Login page

## Troubleshooting

### Error: Database connection failed
- Pastikan environment variables sudah di-set
- Pastikan database accessible dari internet
- Cek firewall/whitelist IP Vercel

### Error: File upload failed
- Gunakan cloud storage untuk file uploads
- Update upload path di code

### Error: Session not working
- Implement JWT authentication
- Atau gunakan database untuk session storage

