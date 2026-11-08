/** Registration and safe editor placeholders, without WordPress or a build step. */
const fs = require('fs');
const vm = require('vm');
const path = require('path');
const registered = {};
const wp = {
  blocks: { registerBlockType: (name, config) => { if (registered[name]) throw new Error('duplicate ' + name); registered[name] = config; } },
  element: { createElement: (type, props, ...children) => ({type, props, children}) },
  blockEditor: { useBlockProps: () => ({ className: 'test-block' }) },
  i18n: { __: s => s }
};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../blocks/account/index.js'), 'utf8'), {window:{wp}});
const names = ['account', ...['dashboard','profile','team','files','messages','participants','certificates','mailing','security'].map(s => 'section-' + s)];
for (const name of names) {
  const block = registered['wppa/' + name];
  if (!block || typeof block.edit !== 'function' || block.save() !== null || block.supports.html !== false) throw new Error('Bad block: ' + name);
  const preview = block.edit();
  if (preview.type !== 'div' || !JSON.stringify(preview).includes('Содержимое отображается посетителю')) throw new Error('Bad editor preview: ' + name);
}
if (Object.keys(registered).length !== names.length) throw new Error('Incorrect block count');
console.log('OK редактор: кабинет и девять разделов, безопасные статические превью');
