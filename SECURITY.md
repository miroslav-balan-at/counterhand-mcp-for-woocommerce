# Security policy

Counterhand is an OAuth 2.1 authorization server and an MCP endpoint in front of
a store's orders and customers, so a vulnerability here is a vulnerability in
every shop that runs it. Please report privately.

## Reporting

Use GitHub's private vulnerability reporting:
<https://github.com/miroslav-balan-at/counterhand-mcp-for-woocommerce/security/advisories/new>.
Only the maintainer can read a report filed there.

Do not open a public issue or a wordpress.org support thread for a security
problem, and do not test against stores you do not own.

Include what you can of: the affected version, the MCP client used, the request
that triggers the problem, and what an attacker gains. A proof of concept
against a local store is welcome.

## What to expect

- Acknowledgement within 72 hours.
- A fix or a mitigation for the current release as fast as its severity
  warrants; wordpress.org holds every release for 24 hours before it reaches
  updaters, so allow for that.
- Credit in the changelog and the advisory, unless you prefer not to be named.

## Scope

In scope: anything that lets a connection do more than the scopes it was
approved for, reach data the approving administrator cannot, bypass the
confirmation gate on risky writes, forge or replay a token, or defeat the
consent flow. Also in scope: the in-admin chat, the action log's PII masking,
and the settings screens.

Out of scope: vulnerabilities in WooCommerce, WordPress or an AI provider
themselves — report those upstream — and issues that require a compromised
administrator account to begin with.
