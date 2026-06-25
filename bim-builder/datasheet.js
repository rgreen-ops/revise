/* Datasheet ingestion: pull text out of a product datasheet (PDF) and
   heuristically extract the commercial/technical fields so the user doesn't have
   to type a part code or description. PDF text extraction uses pdf.js (loaded
   lazily from a CDN). Field heuristics are tuned for typical luminaire datasheets
   and are all editable afterwards — the LDT remains the source of truth for
   photometrics; the datasheet fills the commercial fields (code, name, IP…). */

let pdfjs = null;

export async function extractFromFile(file) {
  const ext = file.name.split('.').pop().toLowerCase();
  let text = '';
  if (ext === 'pdf') {
    text = await pdfText(await file.arrayBuffer());
  } else if (ext === 'txt' || ext === 'csv') {
    text = await file.text();
  } else {
    // images / docx: no text layer we can read in-browser
    return { text: '', fields: {}, note: 'No text layer to read from .' + ext + ' — fill fields manually.' };
  }
  return { text, fields: extractFields(text), note: '' };
}

async function pdfText(buf) {
  if (!pdfjs) {
    pdfjs = await import('pdfjs-dist');
    pdfjs.GlobalWorkerOptions.workerSrc =
      'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/build/pdf.worker.min.mjs';
  }
  const doc = await pdfjs.getDocument({ data: buf }).promise;
  const pages = [];
  for (let p = 1; p <= doc.numPages; p++) {
    const page = await doc.getPage(p);
    const content = await page.getTextContent();
    pages.push(content.items.map((i) => i.str).join(' '));
  }
  return pages.join('\n');
}

/* Pure, testable heuristic extraction. Returns only the fields it is reasonably
   confident about; everything is optional. */
export function extractFields(text) {
  const t = ' ' + text.replace(/\s+/g, ' ').trim() + ' ';
  const out = {};

  const first = (re, idx = 1) => { const m = t.match(re); return m ? m[idx].trim() : undefined; };
  const numNear = (re) => { const m = t.match(re); return m ? parseFloat(m[1].replace(/,/g, '')) : undefined; };

  // Order / part / catalogue code: explicit label, else a code-shaped token.
  out.reference =
    first(/(?:order|cat(?:alogue)?\.?|part|product|article|sku)\s*(?:code|no\.?|number|ref\.?)\s*[:#]?\s*([A-Z0-9][A-Z0-9._\/-]{3,})/i) ||
    first(/\b([A-Z]{2,}[-\/][A-Z0-9][A-Z0-9._\/-]{2,})\b/);

  // Product name / description.
  out.model =
    first(/(?:product\s*name|description|product)\s*[:#]\s*([^\n|]{3,60}?)\s*(?:\||$|datasheet)/i) ||
    first(/\b([A-Z][A-Za-z]+(?:\s+[A-Z0-9][A-Za-z0-9]+){0,3})\s+(?:LED|luminaire|downlight|panel|profile|pendant)\b/);

  out.manufacturer =
    first(/(?:manufacturer|brand|made\s*by)\s*[:#]?\s*([A-Z][\w& ]{2,40}?)\s*(?:\||$)/i);

  // Electrical / photometric (datasheet copies; LDT still wins if present).
  out.wattage = numNear(/(?:power|wattage|input\s*power|connected\s*load)\s*[:#]?\s*([\d.]+)\s*W\b/i)
             ?? numNear(/\b([\d.]+)\s*W(?:atts?)?\b/i);
  out.luminousFlux = numNear(/(?:luminous\s*flux|light\s*output|lumen\s*output|output)\s*[:#]?\s*([\d.,]+)\s*(?:lm|lumens)\b/i)
                  ?? numNear(/\b([\d.,]{2,})\s*(?:lm|lumens)\b/i);
  out.colorTemp = (() => { const m = t.match(/\b(\d{4})\s*K\b/); return m ? parseInt(m[1], 10) : undefined; })();
  out.cri = (() => { const m = t.match(/\b(?:cri|ra)\b\s*[:>=≥]?\s*(\d{2,3})\b/i); return m ? parseInt(m[1], 10) : undefined; })();
  out.ip = first(/\bIP\s?(\d{2})\b/i);
  out.beamAngle = numNear(/(?:beam\s*angle|beam)\s*[:#]?\s*([\d.]+)\s*(?:°|deg)/i);

  // Dimensions L x W x H (mm).
  const dim = t.match(/(\d{2,5})\s*[x×]\s*(\d{2,5})(?:\s*[x×]\s*(\d{2,5}))?\s*mm/i);
  if (dim) out.dimensions = { length: +dim[1], width: +dim[2], height: dim[3] ? +dim[3] : undefined };

  // Drop undefined keys so callers can spread cleanly.
  Object.keys(out).forEach((k) => out[k] === undefined && delete out[k]);
  return out;
}
