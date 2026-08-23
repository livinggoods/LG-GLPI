const fs = require('fs');
const vm = require('vm');

const script = fs.readFileSync('js/afyadesk-user-access.js', 'utf8');

vm.runInNewContext(script, {
  document: {
    readyState: 'complete',
    querySelectorAll: () => [],
  },
  window: {
    location: { pathname: '/front/user.form.php' },
  },
  Event: function Event() {},
});

console.log('AfyaDesk user access script loads successfully.');
