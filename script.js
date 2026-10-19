document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector('form');
    if (!form) return;

    form.addEventListener('submit', function (event) {
        const nameInput = document.querySelector('input[placeholder="Full Name"]');
        const emailInput = document.querySelector('input[placeholder="student@kld.edu.ph"]');
        const studentNumInput = document.querySelector('input[placeholder="202X-XXXXX"]');
        const passwordInputs = document.querySelectorAll('input[type="password"]');

        if (!nameInput || !emailInput || !studentNumInput || passwordInputs.length < 2) return;

        event.preventDefault();

        const name = nameInput.value.trim();
        const email = emailInput.value.trim();
        const studentNum = studentNumInput.value.trim();
        const password = passwordInputs[0].value;
        const confirmPassword = passwordInputs[1].value;

        // 1. Name Validation
        const nameRegex = /^[A-Z][a-zA-Z\s-]*$/;
        if (!nameRegex.test(name)) {
            alert("Invalid Name! Unang letra ay dapat Uppercase, walang numero, at dash (-) lang ang pinapayagang special character.");
            return;
        }

        // 2. Student E-mail Validation
        const emailRegex = /^[a-zA-Z0-9._%+-]+@kld\.edu\.ph$/;
        if (!emailRegex.test(email)) {
            alert("Invalid E-mail! Siguraduhing gamit ang official KLD email (@kld.edu.ph).");
            return;
        }

        // 3. Student Number Validation (Format: 202X-XX-XXXXXX)
        const studentNumRegex = /^202[0-9]-[0-9]{2}-[0-9]{6}$/;
        if (!studentNumRegex.test(studentNum)) {
            alert("Invalid Student Number! Sundin ang format na: 202X-XX-XXXXXX");
            return;
        }

        // 4. Set Password Validation
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,15}$/;
        if (!passwordRegex.test(password)) {
            alert("Invalid Password! Dapat 8-15 characters na may 1 uppercase, 1 lowercase, 1 digit, at 1 special character (@$!%*?&).");
            return;
        }

        // 5. Confirm Password Validation
        if (password !== confirmPassword) {
            alert("Password mismatch! Hindi magkatugma ang Confirm Password sa Set Password.");
            return;
        }

        alert("Sign-Up Successful!");
        form.submit();
    });
});