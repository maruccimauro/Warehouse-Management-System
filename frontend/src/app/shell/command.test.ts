import { parseCommand } from './command';

describe('campo de comando', () => {
  it.each([
    ['su3', { kind: 'open', code: 'SU3', newWindow: false }],
    ['  MIGO ', { kind: 'open', code: 'MIGO', newWindow: false }],
    ['/nSU3', { kind: 'open', code: 'SU3', newWindow: false }],
    ['/N mmbe', { kind: 'open', code: 'MMBE', newWindow: false }],
    ['/oSU3', { kind: 'open', code: 'SU3', newWindow: true }],
    ['/n', { kind: 'menu', newWindow: false }],
    ['/O', { kind: 'menu', newWindow: true }],
    ['', { kind: 'empty' }],
    ['   ', { kind: 'empty' }],
  ])('interpreta %j', (input, expected) => {
    expect(parseCommand(input)).toEqual(expected);
  });

  it.each(['/x', '/iSU3', 'S', 'SU 3', '3SU', '/n/oSU3', 'A'.repeat(21)])('rechaza %j', (input) => {
    expect(parseCommand(input)).toEqual({ kind: 'invalid', input: input.trim() });
  });
});
