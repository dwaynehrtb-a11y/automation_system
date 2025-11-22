// CAR PDF Generator - html2canvas + jsPDF
// Include these in your HTML header:
// <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
// <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

(function() {
'use strict';
/**
 * Generate CAR PDF using html2canvas + jsPDF
 * Optimized for National University CAR format
 */

async function generateCarPdf(classCode, className = '') {
    try {
        showLoadingSpinner('Fetching CAR data...');

        // Fetch CAR HTML from server
        const formData = new FormData();
        formData.append('class_code', classCode);

        console.log('Fetching from: /automation_system/faculty/ajax/download_car_pdf.php');
        
        const response = await fetch('/automation_system/faculty/ajax/download_car_pdf.php', {
            method: 'POST',
            body: formData,
            timeout: 15000
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        console.log('Data received:', data.success ? 'Success' : 'Failed');

        if (!data.success) {
            throw new Error(data.message || 'Failed to generate CAR');
        }

        showLoadingSpinner('Rendering PDF...');

        // Create container for rendering
        const container = document.createElement('div');
        container.style.cssText = `
            position: fixed;
            top: -9999px;
            left: -9999px;
            width: 1400px;
            background: white;
            padding: 0;
            margin: 0;
        `;
        container.innerHTML = data.html;
        document.body.appendChild(container);

        console.log('Container created, waiting for rendering...');

        // Wait longer for rendering
        await new Promise(resolve => setTimeout(resolve, 2000));

        console.log('Starting html2canvas conversion...');
        showLoadingSpinner('Converting to PDF (this may take a moment)...');

        // Single canvas capture with simplified options
        const canvas = await html2canvas(container, {
            scale: 1.5,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#ffffff',
            logging: false,
            windowHeight: container.scrollHeight,
            windowWidth: 1400
        });

        console.log('Canvas created, generating PDF...');

        // Remove container
        document.body.removeChild(container);

        // Create PDF
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({
            orientation: 'landscape',
            unit: 'mm',
            format: 'a4',
            compress: true
        });

        const imgWidth = 297; // A4 landscape width in mm
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        const imgData = canvas.toDataURL('image/jpeg', 0.95);

        // Handle multi-page
        let heightLeft = imgHeight;
        let position = 0;

        pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight);
        heightLeft -= pdf.internal.pageSize.getHeight();

        while (heightLeft > 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight);
            heightLeft -= pdf.internal.pageSize.getHeight();
        }

        // Download and open in new tab
        const fileName = `CAR_${classCode}_${new Date().toISOString().split('T')[0]}.pdf`;
        const pdfUrl = pdf.output('bloburi'); // Generate blob URL for preview
        pdf.save(fileName); // Download the file

        // Open in new tab after a brief delay
        setTimeout(() => {
            window.open(pdfUrl, '_blank');
        }, 500);

        hideLoadingSpinner();
        showSuccessMessage(`PDF "${fileName}" downloaded successfully! Opening preview...`);

        console.log('PDF generated, downloaded, and opened in new tab');

    } catch (error) {
        console.error('PDF Generation Error:', error);
        hideLoadingSpinner();
        showErrorMessage('Error generating PDF: ' + error.message);
    }
}

/**
 * Alternative: Generate single-image PDF (faster, for small CARs)
 */
async function generateCarPdfFast(classCode) {
    try {
        showLoadingSpinner('Generating CAR PDF (Fast Mode)...');

        const formData = new FormData();
        formData.append('class_code', classCode);

        const response = await fetch('/automation_system/faculty/ajax/download_car_pdf.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Failed');
        }

        const container = document.createElement('div');
        container.style.cssText = `
            position: fixed;
            top: -9999px;
            left: -9999px;
            width: 1400px;
            background: white;
        `;
        container.innerHTML = data.html;
        document.body.appendChild(container);

        // Wait for images
        await new Promise(resolve => setTimeout(resolve, 800));

        // Single canvas capture
        const canvas = await html2canvas(container, {
            scale: 1.5,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#ffffff',
            logging: false
        });

        document.body.removeChild(container);

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({
            orientation: 'landscape',
            unit: 'mm',
            format: 'a4'
        });

        const imgWidth = 297;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        const imgData = canvas.toDataURL('image/jpeg', 0.95);

        // Handle multi-page
        let heightLeft = imgHeight;
        let position = 0;

        pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight);
        heightLeft -= pdf.internal.pageSize.getHeight();

        while (heightLeft > 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight);
            heightLeft -= pdf.internal.pageSize.getHeight();
        }

        const fileName = `CAR_${classCode}_${new Date().toISOString().split('T')[0]}.pdf`;
        pdf.save(fileName);

        hideLoadingSpinner();
        showSuccessMessage('PDF downloaded!');

    } catch (error) {
        hideLoadingSpinner();
        showErrorMessage('Error: ' + error.message);
    }
}

/**
 * Preview CAR HTML in modal before generating PDF
 */
async function previewCarPdf(classCode) {
    try {
        showLoadingSpinner('Loading preview...');

        const formData = new FormData();
        formData.append('class_code', classCode);

        const response = await fetch('/automation_system/faculty/ajax/download_car_pdf.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            hideLoadingSpinner();
            
            // Create modal for preview
            const modal = document.createElement('div');
            modal.id = 'car-html-preview-modal';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.7);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10001;
                padding: 20px;
            `;
            
            // A4 Landscape dimensions: 297mm × 210mm (11.69" × 8.27")
            // Make preview card larger (user request) while keeping aspect ratio
            // Use available viewport width, capped to a sensible max
            const aspect = 297/210; // ≈1.414
            const scaledWidth = Math.min(window.innerWidth - 80, 1300); // previously 900
            const scaledHeight = Math.round(scaledWidth / aspect); // maintain landscape ratio
            
            modal.innerHTML = `
                <div style="
                    background: white;
                    width: ${scaledWidth}px;
                    max-width: 95vw;
                    height: ${scaledHeight}px;
                    max-height: 90vh;
                    border-radius: 8px;
                    display: flex;
                    flex-direction: column;
                    box-shadow: 0 25px 50px rgba(0,0,0,0.3);
                    overflow: hidden;
                    box-sizing: border-box;
                    transition: width .25s ease, height .25s ease;
                ">
                    <div style="
                        padding: 15px 20px;
                        border-bottom: 1px solid #ddd;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        background: #f5f5f5;
                        flex-shrink: 0;
                    ">
                        <h3 style="margin: 0; color: #333; font-size: 16px;">CAR Preview - ${classCode}</h3>
                        <div>
                            <button onclick="document.getElementById('car-html-preview-modal').remove()" style="
                                background: #dc3545;
                                color: white;
                                border: none;
                                padding: 8px 16px;
                                border-radius: 4px;
                                cursor: pointer;
                                font-size: 14px;
                                margin-left: 10px;
                                white-space: nowrap;
                            ">Close</button>
                            <button onclick="CAR_PDF.generate('${classCode}'); document.getElementById('car-html-preview-modal').remove();" style="
                                background: #28a745;
                                color: white;
                                border: none;
                                padding: 8px 16px;
                                border-radius: 4px;
                                cursor: pointer;
                                font-size: 14px;
                                margin-left: 10px;
                                white-space: nowrap;
                            ">Download PDF</button>
                        </div>
                    </div>
                    <div style="
                        flex: 1;
                        overflow: auto;
                        padding: 10px;
                        background: #e8e8e8;
                        box-sizing: border-box;
                    ">
                        <div style="
                            background: white;
                            padding: 20px;
                            border: 1px solid #999;
                            margin: 0;
                            width: 100%;
                            height: 100%;
                            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                            overflow: auto;
                            box-sizing: border-box;
                        ">
                            ${data.html}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            console.log('Preview modal opened successfully with A4 landscape dimensions');
        } else {
            hideLoadingSpinner();
            showErrorMessage(data.message || 'Failed to load preview');
        }
    } catch (error) {
        hideLoadingSpinner();
        console.error('Preview Error:', error);
        showErrorMessage('Error loading preview: ' + error.message);
    }
}

// ===== HELPER FUNCTIONS =====

function showLoadingSpinner(message = 'Loading...') {
    if (!document.getElementById('carLoadingSpinner')) {
        const spinner = document.createElement('div');
        spinner.id = 'carLoadingSpinner';
        spinner.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.85);
            color: white;
            padding: 30px 50px;
            border-radius: 10px;
            z-index: 10000;
            text-align: center;
            font-family: Arial, sans-serif;
        `;
        spinner.innerHTML = `
            <div style="font-size: 16px; margin-bottom: 20px; font-weight: 500;">${message}</div>
            <div style="border: 4px solid rgba(255,255,255,0.2); border-top: 4px solid white; border-radius: 50%; width: 50px; height: 50px; animation: carSpin 1s linear infinite; margin: 0 auto;"></div>
            <div style="margin-top: 15px; font-size: 12px; opacity: 0.8;">This may take a few seconds...</div>
            <style>
                @keyframes carSpin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            </style>
        `;
        document.body.appendChild(spinner);
    }
}

function hideLoadingSpinner() {
    const spinner = document.getElementById('carLoadingSpinner');
    if (spinner) {
        spinner.style.transition = 'opacity 0.3s';
        spinner.style.opacity = '0';
        setTimeout(() => spinner.remove(), 300);
    }
}

function showSuccessMessage(message) {
    const alert = document.createElement('div');
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #10b981;
        color: white;
        padding: 15px 20px;
        border-radius: 6px;
        z-index: 9999;
        font-family: Arial, sans-serif;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease;
    `;
    alert.innerHTML = `✓ ${message}`;
    alert.style.cssText += `
        @keyframes slideIn { 
            from { 
                transform: translateX(400px);
                opacity: 0;
            } 
            to { 
                transform: translateX(0);
                opacity: 1;
            } 
        }
    `;
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => alert.remove(), 300);
    }, 4000);
}

function showErrorMessage(message) {
    const alert = document.createElement('div');
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #ef4444;
        color: white;
        padding: 15px 20px;
        border-radius: 6px;
        z-index: 9999;
        font-family: Arial, sans-serif;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    alert.innerHTML = `✕ ${message}`;
    document.body.appendChild(alert);
    
    setTimeout(() => alert.remove(), 5000);
}

// Export functions for external use
window.CAR_PDF = {
    generate: generateCarPdf,
    generateFast: generateCarPdfFast,
    preview: previewCarPdf
};

})();
