/* Minimal store-only (no compression) ZIP writer so a batch of IFC files can be
   downloaded as one archive — no external library, works offline. ASCII/UTF-8
   filenames; fine for .ifc text payloads. */

const CRC_TABLE = (() => {
  const t = new Uint32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    t[n] = c >>> 0;
  }
  return t;
})();

function crc32(bytes) {
  let c = 0xffffffff;
  for (let i = 0; i < bytes.length; i++) c = CRC_TABLE[(c ^ bytes[i]) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}

/* files: [{ name, text }]. Returns a Blob (application/zip). */
export function makeZip(files) {
  const enc = new TextEncoder();
  const chunks = [];
  const central = [];
  let offset = 0;

  const u16 = (v) => new Uint8Array([v & 0xff, (v >>> 8) & 0xff]);
  const u32 = (v) => new Uint8Array([v & 0xff, (v >>> 8) & 0xff, (v >>> 16) & 0xff, (v >>> 24) & 0xff]);
  const push = (arr) => { chunks.push(arr); offset += arr.length; };

  for (const f of files) {
    const nameBytes = enc.encode(f.name);
    const data = enc.encode(f.text);
    const crc = crc32(data);
    const localOffset = offset;

    // local file header
    push(u32(0x04034b50));
    push(u16(20)); push(u16(0)); push(u16(0)); // version, flags, method=store
    push(u16(0)); push(u16(0));                // mod time, date
    push(u32(crc)); push(u32(data.length)); push(u32(data.length));
    push(u16(nameBytes.length)); push(u16(0)); // name len, extra len
    push(nameBytes);
    push(data);

    // central directory record (buffered, written after all files)
    const c = [];
    const cp = (a) => c.push(a);
    cp(u32(0x02014b50));
    cp(u16(20)); cp(u16(20)); cp(u16(0)); cp(u16(0)); // ver made/needed, flags, method
    cp(u16(0)); cp(u16(0));                           // time, date
    cp(u32(crc)); cp(u32(data.length)); cp(u32(data.length));
    cp(u16(nameBytes.length)); cp(u16(0)); cp(u16(0)); // name, extra, comment len
    cp(u16(0)); cp(u16(0)); cp(u32(0));                // disk, int/ext attrs
    cp(u32(localOffset));
    cp(nameBytes);
    central.push(concat(c));
  }

  const centralStart = offset;
  for (const c of central) push(c);
  const centralSize = offset - centralStart;

  // end of central directory
  push(u32(0x06054b50));
  push(u16(0)); push(u16(0));
  push(u16(files.length)); push(u16(files.length));
  push(u32(centralSize)); push(u32(centralStart));
  push(u16(0));

  return new Blob(chunks, { type: 'application/zip' });
}

function concat(arrays) {
  let len = 0;
  for (const a of arrays) len += a.length;
  const out = new Uint8Array(len);
  let o = 0;
  for (const a of arrays) { out.set(a, o); o += a.length; }
  return out;
}
