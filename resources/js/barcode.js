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

const ean13CheckDigit = (value) => Array.from(value, Number)
    .reduce((total, digit, index) => total + digit * (index % 2 === 0 ? 1 : 3), 0) % 10;

export const scannableBarcodeValue = (value) => {
    const barcodeValue = String(value ?? '').trim();

    if (!/^\d{13}$/.test(barcodeValue) || isValidEan13(barcodeValue)) {
        return barcodeValue;
    }

    const barcodePrefix = barcodeValue.slice(0, 12);

    return `${barcodePrefix}${(10 - ean13CheckDigit(barcodePrefix)) % 10}`;
};

const renderEan13 = (svg, value) => {
    const digits = Array.from(value, Number);
    const parity = ean13ParityPatterns[digits[0]];
    const leftBits = digits.slice(1, 7).map((digit, index) => (parity[index] === 'L' ? ean13LeftPatterns : ean13LeftEvenPatterns)[digit]).join('');
    const rightBits = digits.slice(7).map((digit) => ean13RightPatterns[digit]).join('');
    const bits = `101${leftBits}01010${rightBits}101`;
    const quietZone = 12;

    svg.setAttribute('viewBox', `0 0 ${bits.length + quietZone * 2} 100`);
    svg.append(createSvgElement('rect', { width: bits.length + quietZone * 2, height: 100, fill: '#fff' }));

    Array.from(bits).forEach((bit, index) => {
        if (bit !== '1') {
            return;
        }

        const isGuardBar = index < 3 || (index >= 45 && index < 50) || index >= 92;
        svg.append(createSvgElement('rect', {
            x: quietZone + index,
            y: 0,
            width: 1,
            height: isGuardBar ? 100 : 86,
            fill: '#000',
        }));
    });
};

const renderCode128 = (svg, value) => {
    const characterValues = Array.from(value).map((character) => character.charCodeAt(0));
    if (characterValues.some((characterCode) => characterCode < 32 || characterCode > 126)) {
        return false;
    }

    const codes = [104, ...characterValues.map((characterCode) => characterCode - 32)];
    const checksum = codes.reduce((total, code, index) => total + (index === 0 ? code : code * index), 0) % 103;
    codes.push(checksum, 106);

    const quietZone = 12;
    const width = codes.reduce((total, code) => total + Array.from(code128Patterns[code], Number).reduce((sum, module) => sum + module, 0), quietZone * 2);
    let position = quietZone;

    svg.setAttribute('viewBox', `0 0 ${width} 100`);
    svg.append(createSvgElement('rect', { width, height: 100, fill: '#fff' }));

    codes.forEach((code) => {
        let bar = true;

        Array.from(code128Patterns[code], Number).forEach((moduleWidth) => {
            if (bar) {
                svg.append(createSvgElement('rect', {
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

    return true;
};

export const renderBarcodeSvg = (svg, value) => {
    const barcodeValue = scannableBarcodeValue(value);
    svg.replaceChildren();

    if (!barcodeValue) {
        svg.setAttribute('viewBox', '0 0 100 100');

        return 'No barcode';
    }

    if (isValidEan13(barcodeValue)) {
        renderEan13(svg, barcodeValue);

        return 'EAN-13';
    }

    return renderCode128(svg, barcodeValue) ? 'Code 128' : 'Unsupported barcode';
};
