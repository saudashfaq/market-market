  <div class="flex justify-between items-center mb-6">
    <h1 class="text-xl font-bold text-gray-800">Messages Oversight</h1>
    <span class="text-sm text-gray-500">Read-only monitoring</span>
  </div>
  <div class="bg-white shadow rounded-lg overflow-hidden">
    <table class="w-full text-sm text-left text-gray-600">
      <thead class="bg-gray-100 text-gray-700 text-xs uppercase">
        <tr>
          <th class="px-6 py-3">Conversation</th>
          <th class="px-6 py-3">Participants</th>
          <th class="px-6 py-3">Listing</th>
          <th class="px-6 py-3">Last Message</th>
          <th class="px-6 py-3">Status</th>
          <th class="px-6 py-3 text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr class="border-b hover:bg-gray-50">
          <td class="px-6 py-4">CONV-001 <br><span class="text-xs text-gray-500">12 messages</span></td>
          <td class="px-6 py-4">TechCorp → SaaSBuilder</td>
          <td class="px-6 py-4">Project Management Tool<br><span class="text-xs text-gray-400">LST-001</span></td>
          <td class="px-6 py-4">Thanks for the quick transfer!<br><span class="text-xs text-gray-400">22/01/2024, 14:30</span></td>
          <td class="px-6 py-4"><span class="text-green-600 font-medium">Active</span></td>
          <td class="px-6 py-4 text-center"><i class="fa-solid fa-eye text-gray-500 cursor-pointer"></i></td>
        </tr>
        <tr class="border-b hover:bg-gray-50">
          <td class="px-6 py-4">CONV-002 <br><span class="text-xs text-gray-500">8 messages</span></td>
          <td class="px-6 py-4">StartupGroup → FashionBrand</td>
          <td class="px-6 py-4">E-commerce Fashion Store<br><span class="text-xs text-gray-400">LST-002</span></td>
          <td class="px-6 py-4">When will the social media accounts…<br><span class="text-xs text-gray-400">24/01/2024, 09:15</span></td>
          <td class="px-6 py-4"><span class="text-green-600 font-medium">Active</span></td>
          <td class="px-6 py-4 text-center"><i class="fa-solid fa-eye text-gray-500 cursor-pointer"></i></td>
        </tr>
        <tr class="border-b bg-red-50 hover:bg-red-100">
          <td class="px-6 py-4">CONV-003 <br><span class="text-xs text-gray-500">15 messages</span></td>
          <td class="px-6 py-4">DigitalVentures → AppDeveloper</td>
          <td class="px-6 py-4">Fitness Tracking App<br><span class="text-xs text-gray-400">LST-004</span></td>
          <td class="px-6 py-4">This is unacceptable! I want my money…<br><span class="text-xs text-gray-400">22/01/2024, 15:45</span></td>
          <td class="px-6 py-4">
            <span class="text-red-600 font-medium">Disputed</span><br>
            <span class="text-xs text-red-500">Flagged for review</span>
          </td>
          <td class="px-6 py-4 text-center"><i class="fa-solid fa-eye text-gray-500 cursor-pointer"></i></td>
        </tr>
        <tr class="hover:bg-gray-50">
          <td class="px-6 py-4">CONV-004 <br><span class="text-xs text-gray-500">5 messages</span></td>
          <td class="px-6 py-4">ContentAgency → BlogNetwork</td>
          <td class="px-6 py-4">Content Publishing Platform<br><span class="text-xs text-gray-400">LST-005</span></td>
          <td class="px-6 py-4">Looking forward to starting the transfer…<br><span class="text-xs text-gray-400">21/01/2024, 11:20</span></td>
          <td class="px-6 py-4"><span class="text-green-600 font-medium">Active</span></td>
          <td class="px-6 py-4 text-center"><i class="fa-solid fa-eye text-gray-500 cursor-pointer"></i></td>
        </tr>

      </tbody>
    </table>
  </div>
  <div class="grid grid-cols-4 gap-6 mt-6">
    <div class="bg-white shadow rounded-lg p-4 text-center">
      <i class="fa-solid fa-comments text-blue-500 text-xl mb-2"></i>
      <p class="text-lg font-bold">4</p>
      <p class="text-xs text-gray-500">Total Conversations</p>
    </div>
    <div class="bg-white shadow rounded-lg p-4 text-center">
      <i class="fa-solid fa-check text-green-500 text-xl mb-2"></i>
      <p class="text-lg font-bold">3</p>
      <p class="text-xs text-gray-500">Active Chats</p>
    </div>
    <div class="bg-white shadow rounded-lg p-4 text-center">
      <i class="fa-solid fa-flag text-red-500 text-xl mb-2"></i>
      <p class="text-lg font-bold">1</p>
      <p class="text-xs text-gray-500">Flagged</p>
    </div>
    <div class="bg-white shadow rounded-lg p-4 text-center">
      <i class="fa-solid fa-triangle-exclamation text-yellow-500 text-xl mb-2"></i>
      <p class="text-lg font-bold">1</p>
      <p class="text-xs text-gray-500">Disputes</p>
    </div>
  </div>


<script>
// Ensure API_BASE_PATH is set
// Use PathUtils for API base path
if (!window.API_BASE_PATH && typeof BASE !== 'undefined') {
  window.API_BASE_PATH = BASE + 'api';
  console.log('🔧 API_BASE_PATH:', window.API_BASE_PATH);
}
</script>
<script src="<?= BASE ?>js/polling.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  console.log('🚀 Admin Messages polling initialization started');
  
  if (typeof startPolling === 'undefined') {
    console.error('❌ Polling system not loaded');
    return;
  }
  
  // Note: Messages table might not be in polling API yet
  // This is a placeholder for future implementation
  startPolling({
    offers: (newOffers) => {
      console.log('💰 New offers (potential messages):', newOffers.length);
      if (newOffers.length > 0) {
        showNotification(`${newOffers.length} new offer(s) - check messages`, 'info');
      }
    }
  });
  
  function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    const colors = {
      info: 'bg-blue-500',
      success: 'bg-green-500',
      warning: 'bg-yellow-500',
      error: 'bg-red-500'
    };
    
    notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in`;
    notification.innerHTML = `
      <div class="flex items-center gap-2">
        <i class="fas fa-${type === 'success' ? 'check' : 'info'}-circle"></i>
        <span>${message}</span>
      </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
      notification.style.opacity = '0';
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }
});
</script>
