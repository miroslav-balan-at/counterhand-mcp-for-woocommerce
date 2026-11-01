# Agent Skill

`counterhand-woocommerce` teaches an AI assistant how to run a store through this plugin safely: previewing writes, asking before anything customers will see, reporting what can and cannot be undone. It follows the [Agent Skills](https://agentskills.io) standard. It adds no access: the store's settings and each connection's grants still decide everything.

Connect the assistant to your store first; the plugin's Connect tab shows how.

## Claude Code

```bash
git clone --depth 1 https://github.com/miroslav-balan-at/counterhand-mcp-for-woocommerce.git /tmp/counterhand
mkdir -p ~/.claude/skills
cp -R /tmp/counterhand/skills/counterhand-woocommerce ~/.claude/skills/
```

## Cursor

Cursor reads `~/.claude/skills/` too, so the steps above are enough. To keep it to Cursor, copy the folder into `~/.cursor/skills/` instead, or into `.cursor/skills/` in one project.

## Claude.ai

1. Zip the folder, keeping its name: `cd skills && zip -r counterhand-woocommerce.zip counterhand-woocommerce`
2. In Claude, go to Customize > Skills, choose **+**, then **Create skill**, then **Upload a skill**.
3. Upload the ZIP and switch the skill on.

Code execution has to be on, under Settings > Capabilities. On Team and Enterprise plans, an owner may need to allow it.

## Editing

`tests/Unit/Features/WooCommerceTools/Descriptors/ShippedSurfaceTest.php` fails if the skill names a tool that does not ship, or breaks the format's rules on its name, description and fields.
