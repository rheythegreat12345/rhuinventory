@extends('layouts.app')
@section('title', 'Barcode Scanner')
@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a><x-icon name="chevron" class="icon-sm" /> Scanner</div>
        <h1 class="page-title">Scan, stock in, restock & dispense</h1>
        <p class="page-subtitle">Scan a barcode to find a medicine, register and receive a new medicine, replenish stock, or dispense available stock.</p>
    </div>
</div>

<div class="grid grid-2">
    <section class="card" aria-label="Scanned barcode" style="align-self:start">
        <div class="card-header">
            <div>
                <h2 class="card-title">Scanned barcode</h2>
                <p class="card-subtitle">The barcode below updates from the number scanned or entered on this page.</p>
            </div>
            <span class="stat-icon teal"><x-icon name="scan" /></span>
        </div>
        <div class="card-body">
            <div id="barcode-visual" style="min-height:330px;border-radius:14px;background:#08141b;display:grid;place-items:center;padding:32px;color:#d9e8ef;text-align:center">
                <div style="width:min(100%,420px)">
                    <x-icon name="scan" style="width:46px;height:46px;color:#5eead4;margin-bottom:20px" />
                    <svg id="barcode-svg" role="img" aria-label="Barcode preview" preserveAspectRatio="none" shape-rendering="crispEdges" style="display:block;width:100%;height:132px;margin:0 auto 16px;background:#fff;border-radius:6px"></svg>
                    <div id="barcode-number" class="mono" aria-live="polite" style="font-size:18px;letter-spacing:.18em;color:#fff">Scan or enter a barcode</div>
                    <p id="barcode-hint" style="margin:16px 0 0;color:#a9c0cb">The matching barcode lines will appear here.</p>
                </div>
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
                            @if(auth()->user()->hasPermission('medicines.create') && auth()->user()->hasPermission('stock.receive'))
                            <label class="checkbox-row" style="padding:9px 11px;border:1px solid var(--border);border-radius:10px"><input name="scan_action" type="radio" value="new_medicine" @checked(old('scan_action') === 'new_medicine')><span><strong>Stock in new medicine</strong><small class="table-secondary">Scan a new barcode, then enter the medicine details and its first batch.</small></span></label>
                            @endif
                            @if(auth()->user()->hasPermission('stock.receive'))
                            <label class="checkbox-row" style="padding:9px 11px;border:1px solid var(--border);border-radius:10px"><input name="scan_action" type="radio" value="restock" @checked(old('scan_action') === 'restock')><span><strong>Restock this medicine</strong><small class="table-secondary">Open Restock with the scanned medicine selected, ready to replenish low stock.</small></span></label>
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
            <div id="usb-device-status" class="help-text" role="status" aria-live="polite">Checking USB device support...</div>
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
(() => {
const scannerCleanupKey = 'medistockScannerCleanup';
const initializeScanner = () => {
    window[scannerCleanupKey]?.();

    const form = document.getElementById('barcode-form');
    const input = document.getElementById('barcode');
    const barcodeSvg = document.getElementById('barcode-svg');
    const barcodeNumber = document.getElementById('barcode-number');
    const barcodeHint = document.getElementById('barcode-hint');
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
    const lastBarcodeStorageKey = 'medistock-last-scanned-barcode';
    const code128Patterns = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];
    const ean13LeftPatterns = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
    const ean13LeftEvenPatterns = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];
    const ean13RightPatterns = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];
    const ean13ParityPatterns = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];

    let scannerBuffer = '';
    let lastScannerKeyAt = 0;
    let scannerTimer = null;
    let isSubmitting = false;

    const setHardwareStatus = (message, kind = 'warning') => {
        hardwareStatus.className = `alert alert-${kind}`;
        hardwareStatus.querySelector('span').textContent = message;
        hardwareBadge.className = `badge badge-${kind}`;
        hardwareBadge.textContent = kind === 'success' ? 'Scanner ready' : 'Scanner waiting';
    };

    const code128BValues = (value) => {
        const characters = Array.from(value);

        if (characters.some((character) => character.charCodeAt(0) < 32 || character.charCodeAt(0) > 126)) {
            return null;
        }

        return characters.map((character) => character.charCodeAt(0) - 32);
    };

    const createSvgElement = (name, attributes) => {
        const element = document.createElementNS('http://www.w3.org/2000/svg', name);

        Object.entries(attributes).forEach(([attribute, value]) => element.setAttribute(attribute, String(value)));

        return element;
    };

    const isValidEan13 = (value) => {
        if (!/^\d{13}$/.test(value)) {
            return false;
        }

        const checksum = Array.from(value.slice(0, 12), Number)
            .reduce((total, digit, index) => total + digit * (index % 2 === 0 ? 1 : 3), 0);

        return (10 - checksum % 10) % 10 === Number(value.at(-1));
    };

    const renderEan13 = (value) => {
        const digits = Array.from(value, Number);
        const parity = ean13ParityPatterns[digits[0]];
        const leftBits = digits.slice(1, 7).map((digit, index) => (parity[index] === 'L' ? ean13LeftPatterns : ean13LeftEvenPatterns)[digit]).join('');
        const rightBits = digits.slice(7).map((digit) => ean13RightPatterns[digit]).join('');
        const bits = `101${leftBits}01010${rightBits}101`;
        const quietZone = 12;

        barcodeSvg.setAttribute('viewBox', `0 0 ${bits.length + quietZone * 2} 100`);
        barcodeSvg.append(createSvgElement('rect', { width: bits.length + quietZone * 2, height: 100, fill: '#fff' }));

        Array.from(bits).forEach((bit, index) => {
            if (bit !== '1') {
                return;
            }

            const isGuardBar = index < 3 || (index >= 45 && index < 50) || index >= 92;
            barcodeSvg.append(createSvgElement('rect', {
                x: quietZone + index,
                y: 0,
                width: 1,
                height: isGuardBar ? 100 : 86,
                fill: '#000',
            }));
        });
    };

    const renderBarcode = (value) => {
        const barcodeValue = value.trim();
        barcodeSvg.replaceChildren();
        barcodeNumber.textContent = barcodeValue || 'Scan or enter a barcode';

        if (!barcodeValue) {
            barcodeSvg.setAttribute('viewBox', '0 0 100 100');
            barcodeHint.textContent = 'The matching barcode lines will appear here.';
            return;
        }

        try {
            localStorage.setItem(lastBarcodeStorageKey, barcodeValue);
        } catch (_) {
            // The barcode preview still works when browser storage is unavailable.
        }

        if (isValidEan13(barcodeValue)) {
            renderEan13(barcodeValue);
            barcodeHint.textContent = `EAN-13 barcode lines for ${barcodeValue}.`;
            return;
        }

        const characterValues = code128BValues(barcodeValue);
        if (!characterValues) {
            barcodeSvg.setAttribute('viewBox', '0 0 100 100');
            barcodeHint.textContent = 'This value cannot be rendered as a Code 128 barcode.';
            return;
        }

        const codes = [104, ...characterValues];
        const checksum = codes.reduce((total, code, index) => total + (index === 0 ? code : code * index), 0) % 103;
        codes.push(checksum, 106);

        const quietZone = 12;
        const width = codes.reduce((total, code) => total + Array.from(code128Patterns[code], Number).reduce((sum, module) => sum + module, 0), quietZone * 2);
        let position = quietZone;

        barcodeSvg.setAttribute('viewBox', `0 0 ${width} 100`);
        barcodeSvg.append(createSvgElement('rect', { width, height: 100, fill: '#fff' }));

        codes.forEach((code) => {
            let bar = true;

            Array.from(code128Patterns[code], Number).forEach((moduleWidth) => {
                if (bar) {
                    barcodeSvg.append(createSvgElement('rect', {
                        x: position,
                        y: 0,
                        width: moduleWidth,
                        height: 100,
                        fill: '#000',
                    }));
                }

                position += moduleWidth;
                bar = !bar;
            });
        });

        barcodeHint.textContent = `Code 128 barcode for ${barcodeValue}.`;
    };

    const selectedScanAction = () => document.querySelector('input[name="scan_action"]:checked')?.value || 'lookup';

    const scanDestination = () => {
        const action = selectedScanAction();
        if (action === 'new_medicine') return 'Opening new medicine stock-in...';
        if (action === 'restock') return 'Opening Restock...';
        if (action === 'dispense') return 'Opening Dispense...';
        return 'Opening record...';
    };

    const syncScanAction = () => {
        if (!scannerSubmitLabel) {
            return;
        }

        const action = selectedScanAction();
        scannerSubmitLabel.textContent = action === 'new_medicine'
            ? 'Stock in new medicine'
            : action === 'restock'
                ? 'Restock medicine'
                : action === 'dispense'
                    ? 'Dispense medicine'
                    : 'Find medicine';
    };

    const focusScanField = () => {
        input.focus({ preventScroll: true });
        input.select();
    };

    const submitHardwareScan = (barcode) => {
        const scannedBarcode = barcode.trim();

        clearTimeout(scannerTimer);
        scannerBuffer = '';

        if (scannedBarcode.length < minimumScannerLength || isSubmitting) {
            return;
        }

        isSubmitting = true;
        input.value = scannedBarcode;
        renderBarcode(scannedBarcode);
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
                renderBarcode('');
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
                renderBarcode('');
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
        renderBarcode(scannerBuffer);
        input.focus({ preventScroll: true });
        setHardwareStatus('Reading barcode from USB scanner...', 'success');
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

    input.addEventListener('input', () => renderBarcode(input.value));
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

        const action = selectedScanAction();
        const actionStatus = action === 'new_medicine'
            ? `Opening new medicine stock-in for ${barcodeValue}...`
            : action === 'restock'
                ? `Opening Restock for ${barcodeValue}...`
                : action === 'dispense'
                    ? `Opening Dispense for ${barcodeValue}...`
                    : `Looking up ${barcodeValue}...`;

        setHardwareStatus(actionStatus, 'success');
    });

    document.addEventListener('keydown', captureHardwareScan, true);
    window[scannerCleanupKey] = () => {
        clearTimeout(scannerTimer);
        document.removeEventListener('keydown', captureHardwareScan, true);
        delete window[scannerCleanupKey];
    };
    setHardwareStatus('Scanner ready. Scan a barcode or enter a code manually.', 'success');
    let lastScannedBarcode = '';
    try {
        lastScannedBarcode = localStorage.getItem(lastBarcodeStorageKey) || '';
    } catch (_) {
        lastScannedBarcode = '';
    }

    renderBarcode(input.value || lastScannedBarcode);
    syncScanAction();
    focusScanField();
    initialiseUsbScannerMonitoring();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeScanner, { once: true });
} else {
    initializeScanner();
}
})();
</script>
@endpush
