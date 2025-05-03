const fs = require('fs');

/**
 * Fungsi parsing manual untuk CSS besar, simpan aturan terakhir per selektor.
 * @param {string} inputFile
 * @param {string} outputFile
 */
function removeDuplicateCSS(inputFile, outputFile) {
  const css = fs.readFileSync(inputFile, 'utf-8');
  const rules = new Map();

  let buffer = '';
  let inBlock = false;
  let selector = '';
  let braceCount = 0;

  for (let i = 0; i < css.length; i++) {
    const char = css[i];
    buffer += char;

    if (char === '{') {
      if (!inBlock) {
        selector = buffer.slice(0, -1).trim();
        buffer = '';
        inBlock = true;
      }
      braceCount++;
    } else if (char === '}') {
      braceCount--;
      if (braceCount === 0 && inBlock) {
        const properties = buffer.slice(0, -1).trim();
        if (selector && properties) {
          rules.set(selector, properties); // simpan yang terakhir
        }
        buffer = '';
        inBlock = false;
        selector = '';
      }
    }
  }

  // Bangun ulang CSS-nya
  let cleanedCSS = '';
  for (const [sel, prop] of rules.entries()) {
    cleanedCSS += `${sel} {\n  ${prop}\n}\n\n`;
  }

  fs.writeFileSync(outputFile, cleanedCSS.trim());
  console.log(`✅ Berhasil: ${outputFile}`);
}

// CLI
if (process.argv.length !== 4) {
  console.error("Cara pakai: node removeCssDup.js input.css output.css");
  process.exit(1);
}

const [inputFile, outputFile] = process.argv.slice(2);
removeDuplicateCSS(inputFile, outputFile);
