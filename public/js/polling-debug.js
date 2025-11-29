/**
 * Polling Debug Helper
 * Run this in console to debug polling issues
 */

window.debugPolling = function() {
  console.log('🔍 POLLING DEBUG INFO');
  console.log('='.repeat(50));
  
  // 1. Check if polling manager exists
  console.log('\n1️⃣ Polling Manager Status:');
  if (window.pollingManager) {
    console.log('✅ Polling manager exists');
    console.log('   - Is polling:', window.pollingManager.isPolling);
    console.log('   - Error count:', window.pollingManager.errorCount);
    console.log('   - Last check times:', window.pollingManager.lastCheckTimes);
  } else {
    console.log('❌ Polling manager NOT found');
  }
  
  // 2. Check path detection
  console.log('\n2️⃣ Path Detection:');
  console.log('   - Current URL:', window.location.href);
  console.log('   - Pathname:', window.location.pathname);
  console.log('   - Origin:', window.location.origin);
  console.log('   - API_BASE_PATH:', window.API_BASE_PATH || 'NOT SET');
  
  // 3. Try to detect correct path
  console.log('\n3️⃣ Path Detection Logic:');
  const pathname = window.location.pathname;
  let detectedPath = '';
  
  if (pathname.includes('/public/')) {
    detectedPath = pathname.substring(0, pathname.indexOf('/public/'));
    console.log('   - Detected via /public/:', detectedPath);
  } else if (pathname.includes('/modules/')) {
    detectedPath = pathname.substring(0, pathname.indexOf('/modules/'));
    console.log('   - Detected via /modules/:', detectedPath);
  } else if (pathname.includes('/marketplace/')) {
    detectedPath = '/marketplace';
    console.log('   - Detected via /marketplace/:', detectedPath);
  } else {
    detectedPath = '';
    console.log('   - Using root path (empty)');
  }
  
  // 4. Test polling URLs
  console.log('\n4️⃣ Testing Polling URLs:');
  const testUrls = [
    window.location.origin + detectedPath + '/api/polling_integration.php',
    window.location.origin + '/api/polling_integration.php',
    window.location.origin + '/marketplace/api/polling_integration.php'
  ];
  
  testUrls.forEach((url, index) => {
    console.log(`\n   Test ${index + 1}: ${url}`);
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({
        listings: '1970-01-01 00:00:00',
        offers: '1970-01-01 00:00:00',
        orders: '1970-01-01 00:00:00',
        notifications: '1970-01-01 00:00:00'
      })
    })
    .then(response => {
      console.log(`   ✅ Status ${response.status}:`, response.statusText);
      return response.text();
    })
    .then(text => {
      try {
        const data = JSON.parse(text);
        console.log(`   ✅ Valid JSON response:`, data);
      } catch (e) {
        console.log(`   ❌ Invalid JSON. First 200 chars:`, text.substring(0, 200));
      }
    })
    .catch(error => {
      console.log(`   ❌ Error:`, error.message);
    });
  });
  
  // 5. Check session/auth
  console.log('\n5️⃣ Session/Auth Check:');
  fetch(window.location.origin + detectedPath + '/api/check_login_status.php', {
    credentials: 'same-origin'
  })
  .then(response => response.json())
  .then(data => {
    console.log('   - Login status:', data);
  })
  .catch(error => {
    console.log('   ❌ Could not check login status:', error.message);
  });
  
  console.log('\n' + '='.repeat(50));
  console.log('Debug complete! Check results above.');
};

// Auto-run on load
console.log('💡 Polling Debug Helper loaded!');
console.log('💡 Run debugPolling() in console to diagnose issues');
