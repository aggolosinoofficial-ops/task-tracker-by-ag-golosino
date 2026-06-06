// server.js
const { exec } = require('child_process');

function runPythonScript() {
    console.log("Attempting to run Python...");

    // Replace 'script.py' with the actual path to your python file
    exec('python script.py', (error, stdout, stderr) => {
        if (error) {
            console.error(`Execution Error: ${error.message}`);
            return;
        }
        if (stderr) {
            console.error(`Python Errors: ${stderr}`);
            return;
        }
        console.log(`Python Output: ${stdout}`);
    });
}

// Call the function to test it
runPythonScript();