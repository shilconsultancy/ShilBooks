<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Access</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.net.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap');
        .terminal-text {
            font-family: 'Share Tech Mono', monospace;
            color: #00ff41;
            text-shadow: 0 0 5px #00ff41;
        }
        .glitch {
            position: relative;
        }
        .glitch::before, .glitch::after {
            content: attr(data-text);
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .glitch::before {
            left: 2px;
            text-shadow: -2px 0 #ff00c1;
            clip: rect(44px, 450px, 56px, 0);
            animation: glitch-anim 5s infinite linear alternate-reverse;
        }
        .glitch::after {
            left: -2px;
            text-shadow: -2px 0 #00fff9, 2px 2px #ff00c1;
            clip: rect(44px, 450px, 56px, 0);
            animation: glitch-anim2 5s infinite linear alternate-reverse;
        }
        @keyframes glitch-anim {
            0% { clip: rect(31px, 9999px, 94px, 0); }
            10% { clip: rect(112px, 9999px, 76px, 0); }
            20% { clip: rect(85px, 9999px, 77px, 0); }
            30% { clip: rect(27px, 9999px, 97px, 0); }
            40% { clip: rect(64px, 9999px, 98px, 0); }
            50% { clip: rect(61px, 9999px, 85px, 0); }
            60% { clip: rect(99px, 9999px, 114px, 0); }
            70% { clip: rect(34px, 9999px, 115px, 0); }
            80% { clip: rect(98px, 9999px, 129px, 0); }
            90% { clip: rect(43px, 9999px, 96px, 0); }
            100% { clip: rect(82px, 9999px, 64px, 0); }
        }
        @keyframes glitch-anim2 {
            0% { clip: rect(65px, 9999px, 119px, 0); }
            10% { clip: rect(79px, 9999px, 85px, 0); }
            20% { clip: rect(75px, 9999px, 87px, 0); }
            30% { clip: rect(67px, 9999px, 62px, 0); }
            40% { clip: rect(86px, 9999px, 75px, 0); }
            50% { clip: rect(93px, 9999px, 98px, 0); }
            60% { clip: rect(103px, 9999px, 94px, 0); }
            70% { clip: rect(85px, 9999px, 73px, 0); }
            80% { clip: rect(106px, 9999px, 57px, 0); }
            90% { clip: rect(112px, 9999px, 82px, 0); }
            100% { clip: rect(103px, 9999px, 89px, 0); }
        }
        .input-field {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid #00ff41;
            color: #00ff41;
            caret-color: #00ff41;
        }
        .input-field:focus {
            outline: none;
            box-shadow: 0 0 10px #00ff41;
        }
        .btn-hack {
            background: linear-gradient(45deg, #00ff41 0%, #00fff9 100%);
            color: black;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-hack:hover {
            box-shadow: 0 0 15px #00ff41;
            transform: translateY(-2px);
        }
        .scanline {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                to bottom,
                rgba(0, 255, 65, 0) 0%,
                rgba(0, 255, 65, 0.1) 10%,
                rgba(0, 255, 65, 0) 100%
            );
            animation: scanline 8s linear infinite;
            pointer-events: none;
        }
        @keyframes scanline {
            0% { transform: translateY(-100%); }
            100% { transform: translateY(100%); }
        }
    </style>
</head>
<body class="bg-black flex items-center justify-center min-h-screen overflow-hidden">
    <div id="vanta-bg" class="fixed inset-0 z-0"></div>
    <div class="relative z-10 w-full max-w-md px-6 py-8">
        <div class="bg-black bg-opacity-70 backdrop-blur-sm border border-green-500 rounded-lg p-8 shadow-2xl shadow-green-500/20">
            <div class="relative mb-10">
                <div class="glitch text-center" data-text="SYSTEM ACCESS">
                    <h1 class="terminal-text text-4xl font-bold">SYSTEM ACCESS</h1>
                </div>
                <div class="terminal-text text-center text-sm mt-2 opacity-80">[SECURE TERMINAL v4.2.1]</div>
            </div>
            
            <form class="space-y-6">
                <div>
                    <label class="terminal-text block text-sm font-medium mb-2">USERNAME</label>
                    <div class="relative">
                        <i data-feather="user" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-green-500"></i>
                        <input type="text" class="terminal-text input-field w-full pl-10 pr-3 py-3 rounded-md" placeholder="root">
                    </div>
                </div>
                
                <div>
                    <label class="terminal-text block text-sm font-medium mb-2">PASSWORD</label>
                    <div class="relative">
                        <i data-feather="lock" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-green-500"></i>
                        <input type="password" class="terminal-text input-field w-full pl-10 pr-3 py-3 rounded-md" placeholder="••••••••">
                    </div>
                </div>
                
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember" type="checkbox" class="terminal-text h-4 w-4 rounded border-green-500 bg-black text-green-600 focus:ring-green-500">
                        <label for="remember" class="terminal-text ml-2 block text-sm">REMEMBER ME</label>
                    </div>
                    <a href="#" class="terminal-text text-sm hover:text-green-400">FORGOT PASSWORD?</a>
                </div>
                
                <div>
                    <button type="submit" class="btn-hack terminal-text w-full py-3 px-4 rounded-md font-bold uppercase tracking-wider">
                        ACCESS SYSTEM
                    </button>
                </div>
            </form>
            
            <div class="terminal-text text-center mt-6 text-sm opacity-70">
                UNAUTHORIZED ACCESS WILL BE PROSECUTED
            </div>
        </div>
        
        <div class="terminal-text text-center mt-8 text-xs opacity-50">
            <div class="flex justify-center space-x-4">
                <span>CONNECTED</span>
                <span class="flex items-center">
                    <span class="w-2 h-2 bg-green-500 rounded-full mr-1 animate-pulse"></span>
                    SECURE
                </span>
                <span>ENCRYPTED</span>
            </div>
        </div>
    </div>
    
    <div class="scanline"></div>
    
    <script>
        VANTA.NET({
            el: "#vanta-bg",
            mouseControls: true,
            touchControls: true,
            gyroControls: false,
            minHeight: 200.00,
            minWidth: 200.00,
            scale: 1.00,
            scaleMobile: 1.00,
            color: 0x00ff41,
            backgroundColor: 0x0,
            points: 12.00,
            maxDistance: 22.00,
            spacing: 17.00
        });
        
        // Terminal typing effect
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.querySelector('i').style.textShadow = '0 0 10px #00ff41';
                });
                input.addEventListener('blur', function() {
                    this.parentElement.querySelector('i').style.textShadow = 'none';
                });
            });
            
            // Simulate terminal connection
            setTimeout(() => {
                const status = document.createElement('div');
                status.className = 'terminal-text text-xs mt-2 opacity-70';
                status.textContent = '> Establishing secure connection...';
                document.querySelector('form').prepend(status);
                
                setTimeout(() => {
                    status.textContent = '> Connection secured. Ready for authentication.';
                }, 1500);
            }, 1000);
        });
    </script>
</body>
</html>
