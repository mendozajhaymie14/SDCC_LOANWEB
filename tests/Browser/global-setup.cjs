const { execFileSync } = require('child_process');
const path = require('path');

/**
 * Prepares the browser test run:
 *
 *  1. refuses to run against anything but the scratch database, and
 *  2. creates the cooperative member the test verifies against.
 */
module.exports = async () => {
    const script = path.resolve(__dirname, 'fixtures', 'prepare-member.php');

    const output = execFileSync('php', [script], {
        cwd: path.resolve(__dirname, '..', '..'),
        env: { ...process.env, APP_ENV: 'scratch' },
        encoding: 'utf8',
    });

    const result = JSON.parse(output.trim().split('\n').pop());

    if (result.database !== 'sdcc_scratch') {
        throw new Error(
            `Refusing to run the browser test against "${result.database}". ` +
            'Only the scratch database may be used.'
        );
    }

    console.log(`[browser setup] member ${result.member_id} ready on ${result.database}`);
};
