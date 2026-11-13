'use strict';

let locked = 0;
let writes = 0;

function apply(cdTop) {
  if (locked && Math.abs(cdTop - locked) < 8) return;
  locked = cdTop;
  writes += 1;
}

[100, 101, 106, 100, 107, 99].forEach(apply);
if (writes !== 1) {
  console.error('FAIL small changes still moved the page', writes);
  process.exit(1);
}

locked = 0;
writes = 0;
[100, 140].forEach(apply);
if (writes !== 2) {
  console.error('FAIL a real size change was ignored', writes);
  process.exit(1);
}

console.log('OK chrome lock');
