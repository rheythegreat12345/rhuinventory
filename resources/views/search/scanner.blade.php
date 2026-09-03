@extends('layouts.app')
@section('title', 'Barcode Scanner')
@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a><x-icon name="chevron" class="icon-sm" /> Scanner</div>
        <h1 class="page-title">Barcode scanner</h1>
        <p class="page-subtitle">Use a device camera, USB scanner, or manual code entry to open a medicine record quickly.</p>
    </div>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Camera scanner</h2>
                <p class="card-subtitle">Use your device camera (webcam/phone) to visually scan barcodes. Best for mobile devices or computers with webcams.</p>
            </div>
            <span class="stat-icon teal"><x-icon name="scan" /></span>
        </div>
        <div class="card-body">
            <div class="alert alert-info" style="margin-bottom:14px;box-shadow:none">
                <x-icon name="info" />
                <span><strong>Note:</strong> This uses your device camera to visually scan barcodes. USB barcode scanners work through the manual/hardware scanner section below.</span>
            </div>
            <div class="field" style="margin-bottom:14px">
                <label for="camera-select">Camera source</label>
                <select class="select" id="camera-select" disabled>
                    <option value="">Detecting available cameras…</option>
                </select>
                <div class="help-text">A connected barcode-scanner camera is selected automatically when Windows exposes it as a video device.</div>
            </div>
            <div style="position:relative;aspect-ratio:16/10;background:#08141b;border-radius:14px;overflow:hidden;display:grid;place-items:center">
                <video id="scanner-video" style="width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity 0.3s ease" playsinline muted autoplay></video>
                <div id="scanner-placeholder" style="position:absolute;color:#9db0bb;text-align:center"><x-icon name="scan" style="width:50px;height:50px" /><p>Camera preview will appear here</p></div>
                <div style="position:absolute;inset:20%;border:2px solid #5eead4;border-radius:12px;box-shadow:0 0 0 999px rgba(0,0,0,.18)"></div>
                <div id="scanning-indicator" style="position:absolute;top:10px;right:10px;background:rgba(0,0,0,0.7);color:#5eead4;padding:4px 8px;border-radius:4px;font-size:12px;display:none">Scanning…</div>
            </div>
            <div id="scanner-status" class="alert alert-warning" style="margin-top:14px;box-shadow:none" role="status" aria-live="polite"><x-icon name="info" /><span>Detecting camera availability…</span></div>
            <div style="display:flex;gap:9px">
                <button class="btn btn-primary" id="start-scanner" type="button">Start camera</button>
                <button class="btn btn-secondary" id="stop-scanner" type="button" disabled>Stop</button>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Manual / hardware scanner</h2>
                <p class="card-subtitle">USB scanners in keyboard mode are captured automatically, even if focus moves away.</p>
            </div>
            <span id="hardware-scanner-badge" class="badge badge-warning">Preparing scanner</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('scanner.lookup') }}" id="barcode-form">
                @csrf
                <div class="field">
                    <label class="required" for="barcode">Barcode or medicine ID</label>
                    <input class="input mono" id="barcode" name="barcode" value="{{ old('barcode') }}" autofocus required autocomplete="off" placeholder="Scan or enter code">
                    <div class="help-text">The lookup checks both the barcode value and internal medicine ID. A fast scanner input opens the matching record automatically.</div>
                </div>
                @if(auth()->user()->hasPermission('stock.receive') || auth()->user()->hasPermission('stock.release'))
                    <fieldset class="field" style="border:0;margin:16px 0 0;padding:0">
                        <legend style="margin-bottom:6px;color:var(--muted);font-size:11px;font-weight:680">After a successful scan</legend>
                        <div style="display:grid;gap:8px">
                            <label class="checkbox-row" style="padding:9px 11px;border:1px solid var(--border);border-radius:10px"><input name="scan_action" type="radio" value="lookup" @checked(old('scan_action', 'lookup') === 'lookup')><span><strong>Find medicine</strong><small class="table-secondary">Open the medicine record only.</small></span></label>
                            @if(auth()->user()->hasPermission('stock.receive'))
                            <label class="checkbox-row" style="padding:9px 11px;border:1px solid var(--border);border-radius:10px"><input name="scan_action" type="radio" value="stock_in" @checked(old('scan_action') === 'stock_in')><span><strong>Stock in this medicine</strong><small class="table-secondary">Open Stock In with the scanned medicine selected.</small></span></label>
                            @endif
                            @if(auth()->user()->hasPermission('stock.release'))
                            <label class="checkbox-row" style="padding:9px 11px;border:1px solid var(--border);border-radius:10px"><input name="scan_action" type="radio" value="dispense" @checked(old('scan_action') === 'dispense')><span><strong>Dispense medicine</strong><small class="table-secondary">Quick dispense with quantity input.</small></span></label>
                            @endif
                        </div>
                    </fieldset>
                @else
                    <input name="scan_action" type="hidden" value="lookup">
                @endif
                <button class="btn btn-primary" type="submit" style="width:100%;margin-top:16px"><x-icon name="search" class="icon-sm" /> <span id="scanner-submit-label">Find medicine</span></button>
            </form>

            <div id="hardware-scanner-status" class="alert alert-warning" style="margin-top:20px;box-shadow:none" role="status" aria-live="polite"><x-icon name="scan" /><span>Preparing the hardware scanner.</span></div>
            <div id="usb-device-status" class="help-text" role="status" aria-live="polite">Checking USB device support…</div>
            <div style="display:flex;gap:9px;flex-wrap:wrap;margin-top:12px">
                <button class="btn btn-secondary btn-sm" id="focus-scanner" type="button">Focus scan field</button>
                <button class="btn btn-secondary btn-sm" id="detect-usb-scanner" type="button" hidden>Detect USB scanner</button>
            </div>

            <div class="alert alert-success" style="margin-top:20px;box-shadow:none"><x-icon name="check" /><span><strong>Scanner-ready mode.</strong><br>Most USB scanners act like a keyboard. Scan a barcode with an Enter or Tab suffix, or let the automatic scan timeout submit it. USB plug-in and plug-out messages require a supported browser and a one-time device selection.</span></div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
window.addEventListener('DOMContentLoaded', () => {
    const start = document.getElementById('start-scanner');
    const stop = document.getElementById('stop-scanner');
    const video = document.getElementById('scanner-video');
    const cameraStatus = document.getElementById('scanner-status');
    const cameraPlaceholder = document.getElementById('scanner-placeholder');
    const scanningIndicator = document.getElementById('scanning-indicator');
    const cameraSelect = document.getElementById('camera-select');
    const form = document.getElementById('barcode-form');
    const input = document.getElementById('barcode');
    const hardwareStatus = document.getElementById('hardware-scanner-status');
    const hardwareBadge = document.getElementById('hardware-scanner-badge');
    const usbDeviceStatus = document.getElementById('usb-device-status');
    const focusScanner = document.getElementById('focus-scanner');
    const detectUsbScanner = document.getElementById('detect-usb-scanner');
    const scanActionInputs = document.querySelectorAll('input[name="scan_action"]');
    const scannerSubmitLabel = document.getElementById('scanner-submit-label');
    const scanMaximumGap = 150;
    const minimumScannerLength = 3;
    const scannerTimeout = 250;

    let cameraControls = null;
    let codeReader = null;
    let BrowserCodeReader = null;
    let BrowserMultiFormatReader = null;
    let cameraSelectionWasChanged = false;
    let scannerBuffer = '';
    let lastScannerKeyAt = 0;
    let scannerTimer = null;
    let isSubmitting = false;

    const setCameraStatus = (message, kind = 'warning') => {
        cameraStatus.className = `alert alert-${kind}`;
        cameraStatus.querySelector('span').textContent = message;
    };

    const populateCameraOptions = (videoDevices) => {
        const currentDeviceId = cameraSelect.value;
        cameraSelect.innerHTML = '';

        videoDevices.forEach((device, index) => {
            const option = document.createElement('option');
            option.value = device.deviceId;
            option.textContent = device.label || `Camera ${index + 1}`;
            cameraSelect.append(option);
        });

        const scannerCamera = videoDevices.find((device) => /scanner|barcode/i.test(device.label));
        const environmentCamera = videoDevices.find((device) => /back|rear|environment/i.test(device.label));
        const preferredCamera = scannerCamera || environmentCamera;
        const currentCameraExists = videoDevices.some((device) => device.deviceId === currentDeviceId);
        cameraSelect.value = !cameraSelectionWasChanged && preferredCamera
            ? preferredCamera.deviceId
            : (currentCameraExists ? currentDeviceId : (videoDevices[0]?.deviceId || ''));
        cameraSelect.disabled = videoDevices.length < 2;
    };

    const checkCameraSupport = async () => {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setCameraStatus('Camera access not available. Use HTTPS or localhost. Use manual entry or USB scanner.', 'warning');
            start.disabled = true;
            return false;
        }

        if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            setCameraStatus('Camera requires HTTPS or localhost. Use manual entry or USB scanner.', 'warning');
            start.disabled = true;
            return false;
        }

        try {
            if (!BrowserCodeReader || !BrowserMultiFormatReader) {
                ({ BrowserCodeReader, BrowserMultiFormatReader } = await window.loadBarcodeScanner());
            }

            const videoDevices = await BrowserCodeReader.listVideoInputDevices();

            if (videoDevices.length === 0) {
                setCameraStatus('No camera detected on this device. Use manual entry or USB scanner.', 'warning');
                start.disabled = true;
                return false;
            }

            populateCameraOptions(videoDevices);
            start.disabled = false;
            setCameraStatus(`Found ${videoDevices.length} camera(s). Click "Start camera" to begin scanning.`, 'success');
            return true;
        } catch (error) {
            console.error('Camera enumeration error:', error);
            setCameraStatus('The camera scanner could not load. Refresh the page or use the hardware scanner.', 'warning');
            start.disabled = true;
            return false;
        }
    };

    const setHardwareStatus = (message, kind = 'warning') => {
        hardwareStatus.className = `alert alert-${kind}`;
        hardwareStatus.querySelector('span').textContent = message;
        hardwareBadge.className = `badge badge-${kind}`;
        hardwareBadge.textContent = kind === 'success' ? 'Scanner ready' : 'Scanner waiting';
    };

    const selectedScanAction = () => document.querySelector('input[name="scan_action"]:checked')?.value || 'lookup';

    const scanDestination = () => {
        const action = selectedScanAction();
        if (action === 'stock_in') return 'Opening Stock In…';
        if (action === 'dispense') return 'Opening Dispense…';
        return 'Opening record…';
    };

    const syncScanAction = () => {
        if (scannerSubmitLabel) {
            const action = selectedScanAction();
            if (action === 'stock_in') {
                scannerSubmitLabel.textContent = 'Open Stock In';
            } else if (action === 'dispense') {
                scannerSubmitLabel.textContent = 'Dispense Medicine';
            } else {
                scannerSubmitLabel.textContent = 'Find medicine';
            }
        }
    };

    const focusScanField = () => {
        input.focus({ preventScroll: true });
        input.select();
    };

    const halt = () => {
        cameraControls?.stop();
        cameraControls = null;

        if (video.srcObject) {
            video.srcObject.getTracks().forEach((track) => track.stop());
            video.srcObject = null;
        }

        video.style.opacity = '0';
        cameraPlaceholder.hidden = false;
        start.disabled = false;
        stop.disabled = true;
        scanningIndicator.style.display = 'none';
        setCameraStatus('Camera stopped. Press Start camera to scan again.', 'warning');
    };

    const submitHardwareScan = (barcode) => {
        const scannedBarcode = barcode.trim();

        clearTimeout(scannerTimer);
        scannerBuffer = '';

        if (scannedBarcode.length < minimumScannerLength) {
            return;
        }

        if (isSubmitting) {
            return;
        }

        isSubmitting = true;
        input.value = scannedBarcode;
        setHardwareStatus(`Barcode ${scannedBarcode} received. ${scanDestination()}`, 'success');
        form.requestSubmit();

        setTimeout(() => {
            isSubmitting = false;
        }, 2000);
    };

    const queueHardwareScan = () => {
        clearTimeout(scannerTimer);

        scannerTimer = window.setTimeout(() => {
            const timeSinceLastKey = performance.now() - lastScannerKeyAt;
            if (scannerBuffer.length >= minimumScannerLength && timeSinceLastKey <= scanMaximumGap + 100) {
                submitHardwareScan(scannerBuffer);
            } else {
                scannerBuffer = '';
                input.value = '';
                setHardwareStatus('Scan timed out. Please try again.', 'warning');
            }
        }, scannerTimeout);
    };

    const captureHardwareScan = (event) => {
        if (event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }

        const now = performance.now();

        if (event.key === 'Enter' || event.key === 'Tab') {
            if (scannerBuffer.length >= minimumScannerLength && now - lastScannerKeyAt <= scanMaximumGap + 100) {
                event.preventDefault();
                submitHardwareScan(scannerBuffer);
            } else {
                scannerBuffer = '';
                input.value = '';
            }

            return;
        }

        if (event.key.length !== 1) {
            return;
        }

        if (now - lastScannerKeyAt > scanMaximumGap) {
            scannerBuffer = '';
        }

        scannerBuffer += event.key;
        lastScannerKeyAt = now;

        if (scannerBuffer.length < minimumScannerLength) {
            return;
        }

        event.preventDefault();
        input.value = scannerBuffer;
        input.focus({ preventScroll: true });
        setHardwareStatus('Reading barcode from USB scanner…', 'success');
        queueHardwareScan();
    };

    const describeDevice = (device) => device.productName || 'USB scanner';

    const refreshUsbScannerStatus = async () => {
        try {
            const devices = await navigator.hid.getDevices();

            if (devices.length === 0) {
                usbDeviceStatus.textContent = 'No approved USB scanner detected. Keyboard-mode scanners are still ready to scan.';
                return;
            }

            usbDeviceStatus.textContent = `USB scanner connected: ${devices.map(describeDevice).join(', ')}.`;
        } catch (_) {
            usbDeviceStatus.textContent = 'USB scanner status could not be read. Keyboard-mode scanning is still ready.';
        }
    };

    const initialiseUsbScannerMonitoring = () => {
        if (!window.isSecureContext || !('hid' in navigator)) {
            usbDeviceStatus.textContent = 'This browser cannot report USB plug-in or plug-out events. Keyboard-mode scanning is still ready.';
            return;
        }

        detectUsbScanner.hidden = false;
        void refreshUsbScannerStatus();

        navigator.hid.addEventListener('connect', (event) => {
            usbDeviceStatus.textContent = `USB scanner plugged in: ${describeDevice(event.device)}.`;
        });

        navigator.hid.addEventListener('disconnect', (event) => {
            usbDeviceStatus.textContent = `USB scanner unplugged: ${describeDevice(event.device)}.`;
        });

        detectUsbScanner.addEventListener('click', async () => {
            try {
                const devices = await navigator.hid.requestDevice({ filters: [] });

                usbDeviceStatus.textContent = devices.length > 0
                    ? `USB scanner selected: ${devices.map(describeDevice).join(', ')}.`
                    : 'No USB scanner was selected. Keyboard-mode scanning is still ready.';
            } catch (_) {
                usbDeviceStatus.textContent = 'USB scanner selection was cancelled or unavailable. Keyboard-mode scanning is still ready.';
            }
        });
    };

    start.addEventListener('click', async () => {
        if (!await checkCameraSupport()) {
            return;
        }

        try {
            setCameraStatus('Requesting camera access… Allow camera permission when prompted.', 'warning');
            start.disabled = true;
            stop.disabled = false;
            scanningIndicator.style.display = 'block';
            scanningIndicator.textContent = 'Starting camera…';

            const permissionStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            permissionStream.getTracks().forEach((track) => track.stop());
            const availableCameras = await BrowserCodeReader.listVideoInputDevices();
            populateCameraOptions(availableCameras);

            codeReader ??= new BrowserMultiFormatReader(undefined, {
                delayBetweenScanAttempts: 180,
                delayBetweenScanSuccess: 500,
            });

            cameraControls = await codeReader.decodeFromVideoDevice(
                cameraSelect.value || undefined,
                video,
                (result, _error, controls) => {
                    if (!result || isSubmitting) {
                        return;
                    }

                    const detectedCode = result.getText().trim();
                    if (detectedCode.length < minimumScannerLength) {
                        return;
                    }

                    isSubmitting = true;
                    input.value = detectedCode;
                    scanningIndicator.textContent = `Detected: ${detectedCode}`;
                    navigator.vibrate?.(100);
                    controls.stop();
                    cameraControls = null;
                    setCameraStatus(`Barcode detected: ${detectedCode}. ${scanDestination()}`, 'success');
                    form.requestSubmit();
                },
            );

            cameraPlaceholder.hidden = true;
            video.style.opacity = '1';
            scanningIndicator.textContent = 'Scanning…';
            const activeCameraName = cameraSelect.selectedOptions[0]?.textContent || 'Selected camera';
            setCameraStatus(`${activeCameraName} is active. Hold the barcode steady inside the frame.`, 'success');

            const videoDevices = await BrowserCodeReader.listVideoInputDevices();
            if (videoDevices.length > 0) {
                populateCameraOptions(videoDevices);
            }
        } catch (error) {
            console.error('Camera initialization error:', error);
            halt();

            if (error.name === 'NotAllowedError') {
                setCameraStatus('Camera permission denied. Click the camera icon in the address bar, allow access, then try again.', 'warning');
            } else if (error.name === 'NotFoundError') {
                setCameraStatus('No camera found. Connect a webcam or camera-capable scanner.', 'warning');
            } else if (error.name === 'NotReadableError') {
                setCameraStatus('Camera is being used by another app. Close that app and try again.', 'warning');
            } else {
                setCameraStatus(`Camera could not start: ${error.message}`, 'warning');
            }
        }
    });

    stop.addEventListener('click', halt);
    cameraSelect.addEventListener('change', () => {
        cameraSelectionWasChanged = true;

        if (!cameraControls) {
            return;
        }

        halt();
        start.click();
    });
    focusScanner.addEventListener('click', focusScanField);
    scanActionInputs.forEach((scanActionInput) => scanActionInput.addEventListener('change', syncScanAction));
    form.addEventListener('submit', (event) => {
        const barcodeValue = input.value.trim();
        if (!barcodeValue) {
            event.preventDefault();
            setHardwareStatus('Please enter a barcode or medicine code.', 'warning');
            return;
        }

        if (barcodeValue.length < minimumScannerLength) {
            event.preventDefault();
            setHardwareStatus('Barcode must be at least 3 characters.', 'warning');
            return;
        }

        setHardwareStatus(selectedScanAction() === 'stock_in' ? `Opening Stock In for ${barcodeValue}…` : `Looking up ${barcodeValue}…`, 'success');
    });
    document.addEventListener('keydown', captureHardwareScan, true);
    window.addEventListener('beforeunload', halt);

    setHardwareStatus('Scanner ready. Scan a barcode or enter a code manually.', 'success');
    syncScanAction();
    focusScanField();
    initialiseUsbScannerMonitoring();
    void checkCameraSupport();
});
</script>
@endpush
