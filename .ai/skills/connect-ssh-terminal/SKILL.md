---
name: connect-ssh-terminal
description: "Use when: connecting to the project's SSH server, opening an SSH terminal, logging into the remote host, or running commands on the server over SSH."
---

# Connect to SSH Terminal

Use this skill when the user asks to connect to SSH, SSH into the server, open a remote terminal, or run commands on the remote host.

## Inputs

- Optional SSH host alias from `~/.ssh/config`
- Optional `user@host`
- Optional remote command to run after connecting

## Workflow

1. Check `~/.ssh/config` for a matching host alias and prefer `ssh <alias>` when available.
2. If no alias is provided, inspect the project's `.env` for `SSH_HOST` and `SSH_USERNAME`. Treat `SSH_PASSWORD` as sensitive and never print it in chat.
3. Verify SSH is available locally with:

   ```powershell
   ssh -V
   ```

4. Connect from the terminal using either:

   ```powershell
   ssh <alias>
   ```

   or:

   ```powershell
   ssh <user>@<host>
   ```

5. To verify the remote session, run:

   ```bash
   hostname
   pwd
   ```

6. If the session prompts for a password or key confirmation, allow the user to complete the interactive prompt. If non-interactive auth is explicitly needed and credentials are already configured locally, use them without exposing the secret.

## Safety

- Do not print passwords, tokens, or private keys in chat.
- Do not modify `~/.ssh/config` unless the user asks.
- Verify the connection before claiming success.

## Example triggers

- "connect to ssh"
- "ssh into the server"
- "open the SSH terminal"
- "run this on the remote server"
