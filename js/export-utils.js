/**
 * Export Utilities - Fungsi untuk ekspor data ke CSV dan Excel
 */

// Pastikan fungsi tersedia di global scope
(function() {
    'use strict';

// Fungsi untuk mengkonversi data ke CSV
function exportToCSV(data, filename, headers) {
    // Jika data kosong
    if (!data || data.length === 0) {
        alert('Tidak ada data untuk diekspor!');
        return;
    }

    // Buat header CSV
    let csv = '';
    if (headers && headers.length > 0) {
        csv = headers.map(h => `"${h}"`).join(',') + '\n';
    }

    // Tambahkan data
    data.forEach(row => {
        const values = Object.values(row).map(value => {
            // Handle null/undefined
            if (value === null || value === undefined) return '""';
            // Escape quotes dan wrap in quotes
            const str = String(value).replace(/"/g, '""');
            return `"${str}"`;
        });
        csv += values.join(',') + '\n';
    });

    // Buat blob dan download
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', filename || 'export.csv');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Cleanup
    URL.revokeObjectURL(url);
}

// Fungsi untuk mengkonversi data ke Excel (menggunakan SheetJS)
function exportToExcel(data, filename, sheetName, headers) {
    // Cek apakah SheetJS tersedia
    if (typeof XLSX === 'undefined') {
        // Fallback ke CSV jika SheetJS tidak tersedia
        console.warn('SheetJS tidak tersedia, menggunakan CSV sebagai fallback');
        exportToCSV(data, filename.replace('.xlsx', '.csv'), headers);
        return;
    }

    // Jika data kosong
    if (!data || data.length === 0) {
        alert('Tidak ada data untuk diekspor!');
        return;
    }

    // Siapkan data untuk Excel
    const worksheetData = [];
    
    // Tambahkan header jika ada
    if (headers && headers.length > 0) {
        worksheetData.push(headers);
    } else if (data.length > 0) {
        // Gunakan keys dari objek pertama sebagai header
        worksheetData.push(Object.keys(data[0]));
    }

    // Tambahkan data
    data.forEach(row => {
        const values = Object.keys(data[0]).map(key => {
            const value = row[key];
            // Handle null/undefined
            if (value === null || value === undefined) return '';
            return value;
        });
        worksheetData.push(values);
    });

    // Buat workbook dan worksheet
    const worksheet = XLSX.utils.aoa_to_sheet(worksheetData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, sheetName || 'Sheet1');

    // Download file
    XLSX.writeFile(workbook, filename || 'export.xlsx');
}

// Fungsi untuk ekspor laporan reservasi
async function exportReservasiReport(format = 'csv') {
    try {
        // Tampilkan loading indicator
        const loadingMsg = document.createElement('div');
        loadingMsg.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 1rem 2rem; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 9999;';
        loadingMsg.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Mengambil data...';
        document.body.appendChild(loadingMsg);
        
        const response = await fetch('api/reservasi.php');
        if (!response.ok) {
            throw new Error('Gagal mengambil data dari server');
        }
        const data = await response.json();

        if (!data || data.length === 0) {
            document.body.removeChild(loadingMsg);
            alert('Tidak ada data reservasi untuk diekspor!');
            return;
        }

        // Format data untuk ekspor
        const exportData = data.map((item, index) => ({
            'No': index + 1,
            'ID Reservasi': item.reservasi_id,
            'Nama': item.nama_reservasi,
            'Lapangan': item.lapangan,
            'Tanggal': item.tanggal,
            'Jam': item.jam,
            'Status': item.status || 'Menunggu Konfirmasi',
            'User ID': item.user_id
        }));

        document.body.removeChild(loadingMsg);
        
        const headers = ['No', 'ID Reservasi', 'Nama', 'Lapangan', 'Tanggal', 'Jam', 'Status', 'User ID'];
        const timestamp = new Date().toISOString().split('T')[0];
        const filename = `Laporan_Reservasi_${timestamp}`;

        if (format === 'excel') {
            exportToExcel(exportData, `${filename}.xlsx`, 'Data Reservasi', headers);
            console.log('Ekspor Excel berhasil!');
        } else {
            exportToCSV(exportData, `${filename}.csv`, headers);
            console.log('Ekspor CSV berhasil!');
        }
    } catch (error) {
        console.error('Error exporting reservasi:', error);
        const loadingMsg = document.querySelector('div[style*="position: fixed"]');
        if (loadingMsg) {
            document.body.removeChild(loadingMsg);
        }
        alert('Terjadi kesalahan saat mengekspor data: ' + (error.message || 'Unknown error'));
    }
}

// Fungsi untuk ekspor laporan pengguna
async function exportUserReport(format = 'csv') {
    try {
        // Tampilkan loading indicator
        const loadingMsg = document.createElement('div');
        loadingMsg.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 1rem 2rem; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 9999;';
        loadingMsg.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Mengambil data...';
        document.body.appendChild(loadingMsg);
        
        const response = await fetch('api/user.php');
        if (!response.ok) {
            throw new Error('Gagal mengambil data dari server');
        }
        const data = await response.json();

        if (!data || data.length === 0) {
            document.body.removeChild(loadingMsg);
            alert('Tidak ada data pengguna untuk diekspor!');
            return;
        }

        // Format data untuk ekspor
        const exportData = data.map((item, index) => ({
            'No': index + 1,
            'ID User': item.user_id,
            'Username': item.username,
            'Email': item.email,
            'No. Telepon': item.no_tlp || '-',
            'Role': item.role || 'user'
        }));

        document.body.removeChild(loadingMsg);
        
        const headers = ['No', 'ID User', 'Username', 'Email', 'No. Telepon', 'Role'];
        const timestamp = new Date().toISOString().split('T')[0];
        const filename = `Laporan_Pengguna_${timestamp}`;

        if (format === 'excel') {
            exportToExcel(exportData, `${filename}.xlsx`, 'Data Pengguna', headers);
            console.log('Ekspor Excel berhasil!');
        } else {
            exportToCSV(exportData, `${filename}.csv`, headers);
            console.log('Ekspor CSV berhasil!');
        }
    } catch (error) {
        console.error('Error exporting user:', error);
        const loadingMsg = document.querySelector('div[style*="position: fixed"]');
        if (loadingMsg) {
            document.body.removeChild(loadingMsg);
        }
        alert('Terjadi kesalahan saat mengekspor data: ' + (error.message || 'Unknown error'));
    }
}

// Fungsi untuk ekspor laporan statistik
async function exportStatistikReport(format = 'csv') {
    try {
        // Tampilkan loading indicator
        const loadingMsg = document.createElement('div');
        loadingMsg.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 1rem 2rem; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 9999;';
        loadingMsg.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Mengambil data...';
        document.body.appendChild(loadingMsg);
        
        const response = await fetch('api/statistik.php');
        if (!response.ok) {
            throw new Error('Gagal mengambil data dari server');
        }
        const data = await response.json();
        
        document.body.removeChild(loadingMsg);

        // Format data statistik
        const exportData = [{
            'Total Reservasi': data.total_reservasi || 0,
            'Total Pembayaran': `Rp ${Number(data.total_pembayaran || 0).toLocaleString('id-ID')}`,
            'Total Pengguna': data.total_user || 0,
            'Lapangan Digunakan Hari Ini': data.lapangan_hari_ini || 0,
            'Tanggal Laporan': new Date().toLocaleDateString('id-ID')
        }];

        const headers = ['Total Reservasi', 'Total Pembayaran', 'Total Pengguna', 'Lapangan Digunakan Hari Ini', 'Tanggal Laporan'];
        const timestamp = new Date().toISOString().split('T')[0];
        const filename = `Laporan_Statistik_${timestamp}`;

        if (format === 'excel') {
            exportToExcel(exportData, `${filename}.xlsx`, 'Statistik', headers);
            console.log('Ekspor Excel berhasil!');
        } else {
            exportToCSV(exportData, `${filename}.csv`, headers);
            console.log('Ekspor CSV berhasil!');
        }
    } catch (error) {
        console.error('Error exporting statistik:', error);
        const loadingMsg = document.querySelector('div[style*="position: fixed"]');
        if (loadingMsg) {
            document.body.removeChild(loadingMsg);
        }
        alert('Terjadi kesalahan saat mengekspor data: ' + (error.message || 'Unknown error'));
    }
}

// Fungsi untuk ekspor laporan pembayaran
async function exportPembayaranReport(format = 'csv') {
    try {
        // Tampilkan loading indicator
        const loadingMsg = document.createElement('div');
        loadingMsg.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 1rem 2rem; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 9999;';
        loadingMsg.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Mengambil data...';
        document.body.appendChild(loadingMsg);
        
        // Ambil semua reservasi
        const reservasiResponse = await fetch('api/reservasi.php');
        if (!reservasiResponse.ok) {
            throw new Error('Gagal mengambil data reservasi');
        }
        const reservasiData = await reservasiResponse.json();

        if (!reservasiData || reservasiData.length === 0) {
            document.body.removeChild(loadingMsg);
            alert('Tidak ada data pembayaran untuk diekspor!');
            return;
        }

        // Ambil data pembayaran untuk setiap reservasi
        const exportData = [];
        let index = 1;

        for (const reservasi of reservasiData) {
            try {
                const paymentResponse = await fetch(`api/payment.php?reservasi_id=${reservasi.reservasi_id}`);
                const paymentData = await paymentResponse.json();

                if (paymentData && paymentData.status !== false) {
                    exportData.push({
                        'No': index++,
                        'ID Pembayaran': paymentData.pembayaran_id || '-',
                        'ID Reservasi': reservasi.reservasi_id,
                        'Nama': reservasi.nama_reservasi,
                        'Lapangan': reservasi.lapangan,
                        'Tanggal': reservasi.tanggal,
                        'Jam': reservasi.jam,
                        'Total Bayar': paymentData.total_bayar ? `Rp ${Number(paymentData.total_bayar).toLocaleString('id-ID')}` : '-',
                        'Bukti Pembayaran': paymentData.bukti_pembayaran || '-',
                        'Status Reservasi': reservasi.status || 'Menunggu Konfirmasi'
                    });
                }
            } catch (err) {
                // Skip jika tidak ada pembayaran
            }
        }

        if (exportData.length === 0) {
            document.body.removeChild(loadingMsg);
            alert('Tidak ada data pembayaran untuk diekspor!');
            return;
        }

        document.body.removeChild(loadingMsg);
        
        const headers = ['No', 'ID Pembayaran', 'ID Reservasi', 'Nama', 'Lapangan', 'Tanggal', 'Jam', 'Total Bayar', 'Bukti Pembayaran', 'Status Reservasi'];
        const timestamp = new Date().toISOString().split('T')[0];
        const filename = `Laporan_Pembayaran_${timestamp}`;

        if (format === 'excel') {
            exportToExcel(exportData, `${filename}.xlsx`, 'Data Pembayaran', headers);
            console.log('Ekspor Excel berhasil!');
        } else {
            exportToCSV(exportData, `${filename}.csv`, headers);
            console.log('Ekspor CSV berhasil!');
        }
    } catch (error) {
        console.error('Error exporting pembayaran:', error);
        const loadingMsg = document.querySelector('div[style*="position: fixed"]');
        if (loadingMsg) {
            document.body.removeChild(loadingMsg);
        }
        alert('Terjadi kesalahan saat mengekspor data: ' + (error.message || 'Unknown error'));
    }
}

// Export semua fungsi ke global scope
window.exportToCSV = exportToCSV;
window.exportToExcel = exportToExcel;
window.exportReservasiReport = exportReservasiReport;
window.exportUserReport = exportUserReport;
window.exportStatistikReport = exportStatistikReport;
window.exportPembayaranReport = exportPembayaranReport;

// Debug: Log bahwa fungsi sudah dimuat
console.log('Export utilities loaded successfully!');

})();
