/*global window */

/**
 * wpmldev-7978: bootstrap for the explicit media screen commands.
 *
 * The former `current_screen` lifecycle callback mutated media state during
 * plain GET navigation, before the destination screen enforced its own
 * capability checks. The screens now only localize the applicable command
 * (action + action-bound nonce + pass-through args) and this script invokes
 * it over admin-ajax, where the PHP handler authenticates and authorizes
 * everything server-side before writing.
 */
(function () {
  'use strict';

  var config = window.wpmlMediaScreenCommands;

  if (!config || !config.ajaxUrl || !Array.isArray(config.commands)) {
    return;
  }

  config.commands.forEach(function (command) {
    if (!command || !command.action || !command.nonce) {
      return;
    }

    var body = new window.FormData();
    body.append('action', command.action);
    body.append('nonce', command.nonce);

    var args = command.args || {};
    Object.keys(args).forEach(function (key) {
      body.append(key, args[key]);
    });

    window.fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: body
    });
  });
}());
