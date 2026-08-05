/**
 * Minimal `ws`-shaped wrapper over Node's built-in WebSocket.
 *
 * The browser tests were first written against the npm `ws` package. That would mean an
 * npm install and a node_modules tree inside a cloud-synced project folder — exactly the
 * dependency weight this project avoids everywhere else (Tailwind is a standalone binary,
 * Alpine would be a vendored file, Composer carries Twig alone).
 *
 * Node has had a global WebSocket since v22, so the dependency is unnecessary. This shim
 * maps the small slice of the `ws` API the tests use onto it, so the tests run with
 * nothing installed:
 *
 *   on('open' | 'message', fn)   off('message', fn)   send(str)   close()
 *
 * Message handlers receive an object with toString(), because the tests call
 * JSON.parse(raw.toString()) — the built-in delivers a MessageEvent whose .data is
 * already a string.
 *
 * Requires Node 22 or newer. Verified on v26.
 */

'use strict';

if (typeof WebSocket === 'undefined') {
  throw new Error(
    'No global WebSocket. Node 22+ is required to run the browser tests — ' +
    `this is ${process.version}.`,
  );
}

class CompatWebSocket {
  /**
   * @param {string} url CDP WebSocket endpoint.
   * @param {object} [_options] Accepted and ignored, so `{ perMessageDeflate: false }`
   *   from the original call sites stays harmless.
   */
  constructor(url, _options) {
    this._socket = new WebSocket(url);
    /** @type {Map<Function, Function>} caller's handler -> the listener actually bound */
    this._wrapped = new Map();
  }

  on(event, handler) {
    if (event === 'open' || event === 'close') {
      this._socket.addEventListener(event, () => handler(), { once: event === 'open' });
      return this;
    }

    if (event === 'error') {
      this._socket.addEventListener('error', (e) => handler(e));
      return this;
    }

    if (event === 'message') {
      // The tests do JSON.parse(raw.toString()), so hand them something with toString().
      const listener = (e) => handler({ toString: () => e.data });
      this._wrapped.set(handler, listener);
      this._socket.addEventListener('message', listener);
      return this;
    }

    throw new Error(`ws-compat: unsupported event '${event}'`);
  }

  off(event, handler) {
    if (event !== 'message') return this;
    const listener = this._wrapped.get(handler);
    if (listener) {
      this._socket.removeEventListener('message', listener);
      this._wrapped.delete(handler);
    }
    return this;
  }

  send(data) {
    this._socket.send(data);
  }

  close() {
    this._socket.close();
  }
}

module.exports = CompatWebSocket;
