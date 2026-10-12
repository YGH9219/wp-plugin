const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../blocks/pulse-button/editor.js'), 'utf8');

function editor(siteColor) {
  let block;
  const component = name => name;
  const window = {
    personalCtaButtonSettings: { color: siteColor },
    wp: {
      blocks: { registerBlockType: (name, options) => { block = options; } },
      blockEditor: { URLInput: component('URLInput'), InspectorControls: component('InspectorControls'), PanelColorSettings: component('PanelColorSettings'), useBlockProps: props => props },
      components: { TextControl: component('TextControl'), ToggleControl: component('ToggleControl') },
      element: { createElement: (type, props, ...children) => ({ type, props, children }) },
      i18n: { __: text => text },
    },
  };
  vm.runInNewContext(source, { window });
  return block;
}
function find(tree, type) {
  if (tree.type === type) return tree;
  for (const child of tree.children || []) {
    if (child && typeof child === 'object') {
      const found = find(child, type);
      if (found) return found;
    }
  }
}
const block = editor('#2563eb');
let changes;
const attributes = { text: '확인', url: 'https://example.test', openInNewTab: true, nofollow: true, sponsored: true };
const tree = block.edit({ attributes, setAttributes: value => { changes = value; } });
assert.equal(tree.props.style['--pct-button-color'], '#2563eb');
assert.equal(tree.props.style['--pct-button-text-color'], '#ffffff');
assert.equal(find(tree, 'a').props.rel, 'noopener nofollow sponsored');
const settings = find(tree, 'PanelColorSettings').props;
assert.equal(settings.disableAlpha, true);
settings.colorSettings[0].onChange('#abc');
assert.equal(changes.buttonColor, '#abc');
settings.colorSettings[0].onChange(undefined);
assert.equal(changes.buttonColor, '');
settings.colorSettings[1].onChange('#112233');
assert.equal(changes.textColor, '#112233');
settings.colorSettings[1].onChange('url(evil)');
assert.equal(changes.textColor, '');
const custom = block.edit({ attributes: { ...attributes, buttonColor: '#abc', textColor: '#112233' }, setAttributes() {} });
assert.equal(custom.props.style['--pct-button-rgb'], '170, 187, 204');
assert.equal(custom.props.style['--pct-button-text-color'], '#112233');
const invalid = editor('invalid').edit({ attributes: { ...attributes, buttonColor: 'red;display:none', textColor: '#12345678' }, setAttributes() {} });
assert.equal(invalid.props.style['--pct-button-color'], '#0f766e');
assert.equal(invalid.props.style['--pct-button-text-color'], '#ffffff');
assert.equal(block.save(), null);
console.log('Pulse CTA editor colors, reset and existing links are valid.');
