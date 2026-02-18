const path = require('path');
const { exec } = require('child_process')


const run = async (args) => {
	return new Promise(resolve => {
		exec(`php -d display_errors=1 -d log_errors=1 -d error_log=php://stderr -d html_errors=0 -d error_reporting=32767 "${path.join(__dirname, 'pxpros.php')}" ${args}`, { env: { NODE_PROJECT: process.cwd() } }, (error, stdout, stderr) => {
			if (error) {
				const errstr = stdout.trim() || stderr.trim() || `${error}`.trim();
				try { errobj = JSON.parse(errstr); }
				catch(e) { errobj = { success: false, error: errstr }; }
				resolve(errobj);
			} else {
				try { retobj = JSON.parse(stdout); }
				catch(e) { retobj = { success: false, error: "Response parsing error.", response: stdout }; }
				resolve(retobj);
			}
		});
	});
}


const render = async (file) => {
	return run(`"${file}"`);
}


const sitemap = async (dir) => {
	return run(`sitemap "${dir}"`);
}

module.exports = { render, sitemap };