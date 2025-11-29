<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';

require_login();

$pdo = db();
$currentUser = current_user();
$userId = $currentUser['id'];

// 🔹 Create or find conversation if seller_id & listing_id are provided
if (isset($_GET['seller_id']) && isset($_GET['listing_id'])) {
  $seller_id = (int)$_GET['seller_id'];
  $listing_id = (int)$_GET['listing_id'];

  // Debug log
  error_log("Message.php - Creating/Finding conversation: User ID: $userId, Seller ID: $seller_id, Listing ID: $listing_id");

  // Check if user is trying to message themselves
  if ($userId === $seller_id) {
    // Redirect to listing detail page with error message
    $_SESSION['error_message'] = "You cannot message yourself about your own listing.";
    header("Location: ./index.php?p=listingDetail&id=" . $listing_id);
    exit;
  }

  // Check existing conversation between these two users (regardless of listing)
  $check = $pdo->prepare("
    SELECT id, buyer_id, seller_id, listing_id FROM conversations 
    WHERE ((buyer_id = ? AND seller_id = ?) OR (buyer_id = ? AND seller_id = ?))
    LIMIT 1
  ");
  $check->execute([$userId, $seller_id, $seller_id, $userId]);
  $conversation = $check->fetch(PDO::FETCH_ASSOC);

  if ($conversation) {
    error_log("Existing conversation found: ID {$conversation['id']}, Buyer: {$conversation['buyer_id']}, Seller: {$conversation['seller_id']}");
  } else {
    error_log("No existing conversation found between these users");
  }

  if (!$conversation) {
    try {
      $insert = $pdo->prepare("
        INSERT INTO conversations (buyer_id, seller_id, listing_id, last_message_at) 
        VALUES (?, ?, ?, NOW())
      ");
      $insert->execute([$userId, $seller_id, $listing_id]);
      $conversation_id = $pdo->lastInsertId();
      error_log("New conversation created: ID $conversation_id between User $userId and Seller $seller_id");
    } catch (PDOException $e) {
      // If duplicate entry error (conversation was created by another request), fetch it
      if ($e->getCode() == 23000) {
        error_log("Duplicate conversation detected, fetching existing one");
        $check->execute([$userId, $seller_id, $seller_id, $userId]);
        $conversation = $check->fetch(PDO::FETCH_ASSOC);
        $conversation_id = $conversation['id'];
        error_log("Using existing conversation: ID $conversation_id");
      } else {
        error_log("Error creating conversation: " . $e->getMessage());
        throw $e;
      }
    }
  } else {
    $conversation_id = $conversation['id'];
    error_log("Using existing conversation: ID $conversation_id between User $userId and Seller $seller_id");
  }

  error_log("Redirecting to conversation ID: $conversation_id");
  header("Location: ./index.php?p=dashboard&page=message&conversation_id=" . $conversation_id);
  exit;
}
?>
<!-- Enhanced Professional Messaging Interface - Mobile Responsive -->
<div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 p-2 sm:p-4">
  <div class="max-w-7xl mx-auto">

    <!-- Enhanced Header - Mobile Responsive -->
    <div class="mb-4 sm:mb-6">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-0">
        <!-- Title Section -->
        <div class="w-full sm:w-auto">
          <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent mb-1">Messages</h1>
          <p class="text-gray-600 text-sm sm:text-base md:text-lg">Stay connected with your conversations</p>
        </div>

        <!-- Notification Bell - Mobile Responsive -->
        <div class="flex items-center space-x-2 sm:space-x-4 w-full sm:w-auto justify-end">
          <div class="bg-white rounded-lg sm:rounded-xl p-2 shadow-sm">
            <i class="fas fa-bell text-gray-500 text-base sm:text-lg"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Professional Mobile-First Chat Interface -->
    <div class="bg-white rounded-xl sm:rounded-2xl md:rounded-3xl shadow-lg sm:shadow-xl md:shadow-2xl overflow-hidden h-[calc(100vh-140px)] sm:h-[calc(100vh-180px)] md:h-[calc(100vh-200px)] border border-gray-200">
      <div class="flex h-full">
        <!-- Conversations Sidebar - Mobile Optimized -->
        <div id="conversationsSidebar" class="w-full md:w-80 lg:w-96 md:border-r border-gray-200 flex flex-col bg-white">
          <!-- Sidebar Header - Clean Mobile Design -->
          <div class="p-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-md">
                  <i class="fas fa-comments text-white text-lg"></i>
                </div>
                <div>
                  <h2 class="text-lg font-bold text-gray-900">Messages</h2>
                  <p class="text-xs text-gray-500">Stay connected</p>
                </div>
              </div>
              <div class="flex items-center space-x-2">
                <span id="conversationCount" class="px-2.5 py-1 bg-blue-500 text-white text-xs font-bold rounded-full shadow-sm">0</span>
                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
              </div>
            </div>
          </div>

          <!-- Conversations List - Mobile Optimized -->
          <div id="conversations" class="flex-1 overflow-y-auto bg-gray-50">
            <div id="conversationList" class="divide-y divide-gray-100"></div>
            <div id="loadingConversations" class="flex flex-col items-center justify-center p-8 hidden">
              <div class="animate-spin rounded-full h-10 w-10 border-4 border-blue-500 border-t-transparent"></div>
              <p class="mt-3 text-sm text-gray-500">Loading chats...</p>
            </div>
            <!-- Empty State -->
            <div id="emptyConversations" class="hidden flex flex-col items-center justify-center p-8 text-center">
              <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-inbox text-gray-400 text-2xl"></i>
              </div>
              <h3 class="text-lg font-semibold text-gray-700 mb-2">No conversations yet</h3>
              <p class="text-sm text-gray-500 mb-4">Start chatting with sellers</p>
              <a href="index.php?p=listing" class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-600 transition-colors">
                Browse Listings
              </a>
            </div>
          </div>
        </div>

        <!-- Chat Area - Professional Mobile Design -->
        <div id="chatBox" class="flex-1 flex flex-col hidden md:flex bg-white">
          <!-- Chat Header - Mobile Optimized -->
          <div id="chatHeader" class="p-3 md:p-4 border-b border-gray-200 bg-white shadow-sm">
            <div class="flex items-center gap-3">
              <!-- Back Button - Mobile Only with Better Design -->
              <button id="backToConversations" class="md:hidden p-2.5 hover:bg-gray-100 rounded-xl transition-colors active:scale-95">
                <i class="fas fa-arrow-left text-gray-700 text-lg"></i>
              </button>

              <!-- User Info with Profile Photo -->
              <div class="flex items-center space-x-3 flex-1 min-w-0">
                <div id="chatAvatar" class="w-10 h-10 md:w-12 md:h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center shadow-md flex-shrink-0 overflow-hidden">
                  <span id="chatInitials" class="text-white font-bold text-sm"></span>
                </div>
                <div class="min-w-0 flex-1">
                  <h3 id="chatName" class="font-bold text-gray-900 text-base md:text-lg truncate">Select a conversation</h3>
                  <p id="chatStatus" class="text-xs md:text-sm text-gray-500 flex items-center truncate">
                    <span class="truncate">Choose a chat to start</span>
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Messages Area - Mobile Optimized -->
          <div id="chatMessages" class="flex-1 overflow-y-auto p-3 md:p-4 space-y-3 bg-gray-50">
            <!-- Empty State - Mobile Friendly -->
            <div id="noChat" class="flex flex-col items-center justify-center h-full text-center p-6">
              <div class="w-24 h-24 md:w-32 md:h-32 bg-gradient-to-br from-blue-100 to-purple-100 rounded-full flex items-center justify-center mb-6 shadow-lg">
                <i class="fas fa-comments text-blue-500 text-3xl md:text-4xl"></i>
              </div>
              <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">No Chat Selected</h3>
              <p class="text-sm md:text-base text-gray-500 max-w-sm mb-6">Select a conversation to start messaging</p>
              <a href="index.php?p=listing" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl hover:from-blue-600 hover:to-purple-700 transition-all font-semibold shadow-lg text-sm">
                <i class="fas fa-search mr-2"></i>
                Browse Listings
              </a>
            </div>
          </div>

          <!-- Message Input - Mobile Optimized -->
          <form id="sendMessageForm" class="p-3 md:p-4 border-t border-gray-200 bg-white hidden">
            <div class="flex items-end gap-2">
              <!-- Attachment & Emoji Buttons - Compact on Mobile -->
              <div class="flex items-center gap-1">
                <button type="button" id="imageButton" class="p-2 md:p-2.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all active:scale-95">
                  <i class="fas fa-image text-lg md:text-xl"></i>
                </button>
                <button type="button" id="emojiButton" class="p-2 md:p-2.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all active:scale-95">
                  <i class="fas fa-smile text-lg md:text-xl"></i>
                </button>
              </div>

              <!-- Message Input - Mobile Friendly -->
              <div class="flex-1 relative">
                <textarea id="messageInput" rows="1" placeholder="Type a message..."
                  class="w-full resize-none border-2 border-gray-300 rounded-xl px-3 md:px-4 py-2.5 md:py-3 text-sm md:text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 max-h-24 overflow-y-auto text-gray-700 placeholder-gray-400 transition-all"></textarea>
                <input type="file" id="imageInput" accept="image/*" class="hidden" multiple>
              </div>

              <!-- Send Button - Mobile Optimized -->
              <button type="submit" id="sendButton" class="p-2.5 md:p-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl hover:from-blue-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-md active:scale-95">
                <i class="fas fa-paper-plane text-lg md:text-xl"></i>
              </button>
            </div>

            <!-- Image Preview Area -->
            <div id="imagePreview" class="hidden mt-3 p-3 bg-gray-50 rounded-lg border">
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700">Selected Images</span>
                <button type="button" id="clearImages" class="text-red-500 hover:text-red-700 text-sm">Clear All</button>
              </div>
              <div id="imagePreviewContainer" class="flex flex-wrap gap-2"></div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Enhanced Emoji Picker -->
<div id="emojiPicker" class="hidden absolute bg-white border border-gray-200 rounded-lg shadow-xl p-3 z-50" style="width: 320px; max-height: 300px; overflow-y: auto;">
  <div class="grid grid-cols-8 gap-1">
    <button type="button" class="emoji-btn p-2 hover:bg-gray-100 rounded text-lg cursor-pointer transition-colors">😀</button>
    <button type="button" class="emoji-btn p-2 hover:bg-gray-100 rounded text-lg cursor-pointer transition-colors">😂</button>
    <button type="button" class="emoji-btn p-2 hover:bg-gray-100 rounded text-lg cursor-pointer transition-colors">😍</button>
    <button type="button" class="emoji-btn p-2 hover:bg-gray-100 rounded text-lg cursor-pointer transition-colors">🥰</button>
    <button type="button" class="emoji-btn p-2 hover:bg-gray-100 rounded text-lg cursor-pointer transition-colors">😊</button>
    <button type="button" class="emoji-btn p-2 hover:bg-gray-100 rounded text-lg cursor-pointer transition-colors">😎</button>
    <button type="button" class="emoji-btn p-2 hover:bg-gray-100 rounded text-lg cursor-pointer transition-colors">🤔</button>
    <button type="button" class="emoji-btn p-2 hover:bg-gray-100 rounded text-lg cursor-pointer transition-colors">😢</button>
    <button type="button" class="emoji-btn p-2 hover:bg-gray-100 rounded text-lg cursor-pointer transition-colors">😭</button>
    <button type="button" class="emoji-btn p-2 hover:bg-gray-100 rounded text-lg cursor-pointer transition-colors">😡</button>
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!allowedTypes.includes(file.type)) {
    showNotification('Please select a valid image file (JPEG, PNG, GIF, WebP)', 'error');
    return false;
    }

    if (file.size > maxSize) {
    showNotification('Image size must be less than 5MB', 'error');
    return false;
    }

    return true;
    }

    function handleImageSelection(files) {
    Array.from(files).forEach(file => {
    if (!validateImage(file)) return;

    const reader = new FileReader();
    reader.onload = function(e) {
    selectedImages.push({
    file: file,
    dataUrl: e.target.result,
    name: file.name,
    size: file.size
    });
    updateImagePreview();
    };
    reader.readAsDataURL(file);
    });
    }

    function updateImagePreview() {
    const previewArea = document.getElementById('imagePreview');
    const container = document.getElementById('imagePreviewContainer');

    if (selectedImages.length === 0) {
    previewArea.classList.add('hidden');
    return;
    }

    previewArea.classList.remove('hidden');
    container.innerHTML = '';

    selectedImages.forEach((imageData, index) => {
    const imageDiv = document.createElement('div');
    imageDiv.className = 'relative group';
    imageDiv.innerHTML = `
    <div class="relative">
      <img src="${imageData.dataUrl}" alt="Preview"
        class="w-20 h-20 object-cover rounded-lg border border-gray-200">
      <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white text-xs p-1 rounded-b-lg">
        ${(imageData.size / 1024).toFixed(1)}KB
      </div>
      <button type="button" onclick="removeImage(${index})"
        class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full text-xs hover:bg-red-600 transition-colors flex items-center justify-center">
        ×
      </button>
    </div>
    `;
    container.appendChild(imageDiv);
    });
    }

    function removeImage(index) {
    selectedImages.splice(index, 1);
    updateImagePreview();
    }

    // Enhanced Message Sending
    async function sendMessage(formData) {
    try {
    const res = await fetch(`${BASE}index.php?p=send_message`, {
    method: 'POST',
    body: formData
    });

    if (!res.ok) {
    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    }

    const result = await res.json();

    if (result.success) {
    return result;
    } else {
    throw new Error(result.error || 'Failed to send message');
    }
    } catch (error) {
    console.error('Send message error:', error);
    throw error;
    }
    }

    // Enhanced Conversation Management
    async function openConversation(id, name, profilePic = null, listingId = null) {
    currentConversation = id;
    currentListingId = listingId;

    // Mobile: Show chat area and hide sidebar
    if (window.innerWidth < 768) {
      document.getElementById('conversationsSidebar').classList.add('hidden');
      document.getElementById('chatBox').classList.remove('hidden');
      document.getElementById('chatBox').classList.add('flex');
      }

      // Immediately remove unread badge from UI
      const conversationEl=document.querySelector(`[data-conversation-id="${id}" ]`);
      if (conversationEl) {
      const unreadBadge=conversationEl.querySelector('.bg-red-500');
      if (unreadBadge) {
      unreadBadge.remove();
      }

      // Remove bold styling from last message
      const lastMessage=conversationEl.querySelector('.font-semibold');
      if (lastMessage && lastMessage.classList.contains('text-gray-900')) {
      lastMessage.classList.remove('text-gray-900', 'font-semibold' );
      lastMessage.classList.add('text-gray-600');
      }
      }

      // Set current conversation FIRST
      currentConversation=id;
      console.log('Current conversation set to:', currentConversation);

      // Update UI
      updateChatHeader(name, profilePic, listingId);
      showChatInterface();
      updateActiveConversation(id);

      // Load messages
      await loadMessages(id);

      // Mark messages as read in background (non-blocking)
      markConversationAsRead(id).catch(error=> {
      console.error('Failed to mark conversation as read:', error);
      });
      }

      function updateChatHeader(name, profilePic, listingId) {
      const chatAvatar = document.getElementById('chatAvatar');

      // Update avatar with profile photo
      if (profilePic && profilePic.trim() !== '' && profilePic !== 'null' && profilePic !== 'undefined') {
      const imageUrl = profilePic.startsWith('http') ? profilePic : `${BASE}${profilePic}`;
      chatAvatar.innerHTML = `
      <img src="${imageUrl}"
        alt="${name || 'User'}"
        class="w-full h-full object-cover"
        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
      <span class="text-white font-bold text-sm hidden">${name ? name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) : '?'}</span>
      `;
      } else {
      // Fallback to initials
      const initials = name ? name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) : '?';
      chatAvatar.innerHTML = `<span class="text-white font-bold text-sm">${initials}</span>`;
      }

      document.getElementById('chatName').textContent = name;

      // Clear status when chat is active - no status text needed
      const chatStatus = document.getElementById('chatStatus');
      chatStatus.innerHTML = ``;
      }

      function showChatInterface() {
      document.getElementById('noChat').style.display = 'none';
      document.getElementById('sendMessageForm').classList.remove('hidden');
      }

      function updateActiveConversation(conversationId) {
      console.log('Updating active conversation to:', conversationId);

      // Update all conversations
      document.querySelectorAll('#conversationList > div').forEach(el => {
      const isActive = el.getAttribute('data-conversation-id') == conversationId;
      const convId = el.getAttribute('data-conversation-id');

      console.log('Conversation', convId, 'isActive:', isActive);

      // Reset all classes first
      el.className = `p-5 cursor-pointer transition-all duration-300 border-l-4 ${
      isActive
      ? 'bg-blue-50 border-blue-500 shadow-md'
      : 'bg-white border-transparent hover:border-blue-200 hover:shadow-sm hover:bg-gray-50'
      }`;

      // Clear unread count badge for active conversation
      if (isActive) {
      const unreadBadge = el.querySelector('.bg-red-500');
      if (unreadBadge) {
      unreadBadge.remove();
      console.log('Unread badge removed for conversation:', conversationId);
      }

      // Remove bold styling from last message
      const lastMessage = el.querySelector('.font-semibold');
      if (lastMessage && lastMessage.classList.contains('text-gray-900')) {
      lastMessage.classList.remove('text-gray-900', 'font-semibold');
      lastMessage.classList.add('text-gray-600');
      }
      }
      });
      }

      // Mark conversation as read (background server update)
      async function markConversationAsRead(conversationId) {
      try {
      const response = await fetch(`${BASE}index.php?p=mark_read`, {
      method: 'POST',
      headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `conversation_id=${conversationId}`
      });

      if (response.ok) {
      const result = await response.json();
      if (result.success) {
      console.log('Conversation marked as read on server:', conversationId, 'Messages marked:', result.marked_count);

      // Update sidebar badge
      if (typeof updateSidebarMessageBadge === 'function') {
      updateSidebarMessageBadge();
      }
      } else {
      console.error('Failed to mark conversation as read:', result.error);
      }
      } else {
      console.error('Failed to mark conversation as read on server, status:', response.status);
      }
      } catch (error) {
      console.error('Error marking conversation as read:', error);
      }
      }

      // Enhanced Message Display with Professional Styling
      function createMessageElement(msg, isOwn) {
      const time = formatTime(msg.created_at);
      const {
      messageText,
      images
      } = parseMessageContent(msg.message);

      const senderProfileHtml = !isOwn ? getProfilePicHtml(msg.sender_profile_pic, msg.sender_name, 'w-10 h-10') : '';

      return `
      <div class="flex ${isOwn ? 'justify-end' : 'justify-start'} mb-4">
        <div class="flex ${isOwn ? 'flex-row-reverse' : 'flex-row'} items-end space-x-3 max-w-xs lg:max-w-lg">
          ${senderProfileHtml}
          <div class="group">
            <div class="${isOwn 
            ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-2xl rounded-br-md shadow-lg' 
            : 'bg-white text-gray-800 rounded-2xl rounded-bl-md shadow-lg border border-gray-100'
          } px-5 py-3 break-words transform transition-all duration-200 hover:scale-105">
              ${messageText ? `<p class="text-sm leading-relaxed font-medium">${messageText}</p>` : ''}
              ${images.length > 0 ? createImagesHtml(images) : ''}
            </div>
            <div class="flex ${isOwn ? 'justify-end' : 'justify-start'} mt-2 opacity-0 group-hover:opacity-100 transition-all duration-200">
              <span class="text-xs ${isOwn ? 'text-blue-300' : 'text-gray-500'} font-medium bg-white bg-opacity-20 px-2 py-1 rounded-full">${time}</span>
            </div>
          </div>
        </div>
      </div>
      `;
      }

      function parseMessageContent(message) {
      let messageText = message;
      let images = [];

      if (message && message.includes('[IMAGES]')) {
      const parts = message.split('[IMAGES]');
      messageText = parts[0].trim();
      if (parts[1]) {
      try {
      images = JSON.parse(parts[1]);
      } catch (e) {
      console.error('Error parsing images:', e);
      }
      }
      }

      return {
      messageText,
      images
      };
      }

      function createImagesHtml(images) {
      return `
      <div class="mt-2 space-y-2">
        ${images.map(img => {
        const fullImageUrl = BASE + '../' + img;
        return `
        <div class="image-container">
          <img src="${fullImageUrl}" alt="Image"
            class="max-w-full h-auto rounded-lg cursor-pointer hover:opacity-90 transition-opacity max-h-64 object-contain"
            onclick="openImageModal('${fullImageUrl}')"
            onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
            onload="console.log('Image loaded:', '${fullImageUrl}')">
          <div style="display:none;" class="p-3 bg-gray-100 rounded-lg text-sm text-gray-600 border border-gray-300">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
              </svg>
              <span class="font-medium">Image not available</span>
            </div>
          </div>
        </div>
        `;
        }).join('')}
      </div>
      `;
      }

      // Event Listeners
      document.addEventListener('DOMContentLoaded', function() {
      initializeEventListeners();
      loadConversations();

      // Check if conversation_id is in URL and open it
      const urlParams = new URLSearchParams(window.location.search);
      const conversationId = urlParams.get('conversation_id');
      if (conversationId) {
      console.log('Opening conversation from URL:', conversationId);
      // Wait for conversations to load first
      setTimeout(() => {
      const convElement = document.querySelector(`[data-conversation-id="${conversationId}"]`);
      if (convElement) {
      convElement.click();
      }
      }, 500);
      }
      });

      function initializeEventListeners() {
      // Mobile: Back to conversations button
      const backBtn = document.getElementById('backToConversations');
      if (backBtn) {
      backBtn.addEventListener('click', () => {
      document.getElementById('conversationsSidebar').classList.remove('hidden');
      document.getElementById('chatBox').classList.add('hidden', 'md:flex');
      });
      }

      // Message form submission
      document.getElementById('sendMessageForm').addEventListener('submit', handleMessageSubmit);

      // Image upload
      document.getElementById('imageButton').addEventListener('click', () => {
      document.getElementById('imageInput').click();
      });

      document.getElementById('imageInput').addEventListener('change', (e) => {
      handleImageSelection(e.target.files);
      e.target.value = ''; // Reset input
      });

      // Emoji picker
      document.getElementById('emojiButton').addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (emojiPickerVisible) {
      hideEmojiPicker();
      } else {
      showEmojiPicker();
      }
      });

      // Emoji selection
      document.querySelectorAll('.emoji-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
      const emoji = e.target.textContent;
      const messageInput = document.getElementById('messageInput');
      const cursorPos = messageInput.selectionStart;
      const textBefore = messageInput.value.substring(0, cursorPos);
      const textAfter = messageInput.value.substring(messageInput.selectionEnd);

      messageInput.value = textBefore + emoji + textAfter;
      messageInput.focus();
      messageInput.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);

      hideEmojiPicker();
      });
      });

      // Clear images
      document.getElementById('clearImages').addEventListener('click', () => {
      selectedImages = [];
      updateImagePreview();
      });

      // Close emoji picker when clicking outside
      document.addEventListener('click', (e) => {
      if (emojiPickerVisible && !e.target.closest('#emojiPicker') && !e.target.closest('#emojiButton')) {
      hideEmojiPicker();
      }
      });

      // Auto-resize textarea
      document.getElementById('messageInput').addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 120) + 'px';
      });

      // Send message on Enter (but not Shift+Enter)
      document.getElementById('messageInput').addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      document.getElementById('sendMessageForm').dispatchEvent(new Event('submit'));
      }
      });

      // Search functionality removed - no search input in header
      }

      async function handleMessageSubmit(e) {
      e.preventDefault();

      const input = document.getElementById('messageInput');
      const sendButton = document.getElementById('sendButton');
      const message = input.value.trim();

      if ((!message && selectedImages.length === 0) || !currentConversation) {
      showNotification('Please enter a message or select an image', 'warning');
      return;
      }

      // Disable send button and show loading
      sendButton.disabled = true;
      sendButton.innerHTML = createLoadingSpinner();

      try {
      const formData = new FormData();
      formData.append('conversation_id', currentConversation);
      formData.append('message', message);

      // Add images to form data
      selectedImages.forEach((imageData, index) => {
      formData.append(`images[${index}]`, imageData.file);
      });

      const result = await sendMessage(formData);

      // Clear input and reset UI
      input.value = '';
      input.style.height = 'auto';
      selectedImages = [];
      updateImagePreview();

      // Reload messages and conversations
      await loadMessages(currentConversation);
      await loadConversations();

      showNotification('Message sent successfully', 'success');

      } catch (error) {
      console.error('Message send error:', error);
      showNotification('Failed to send message: ' + error.message, 'error');
      } finally {
      // Re-enable send button
      sendButton.disabled = false;
      sendButton.innerHTML = `
      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
      </svg>
      `;
      }
      }

      // Utility Functions
      function createLoadingSpinner() {
      return `
      <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      `;
      }

      function showNotification(message, type = 'info') {
      // Remove existing notifications
      document.querySelectorAll('.notification').forEach(el => el.remove());

      const notification = document.createElement('div');
      const bgColor = type === 'error' ? 'bg-red-500' :
      type === 'success' ? 'bg-green-500' :
      type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500';

      notification.className = `notification fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 text-white ${bgColor}`;
      notification.textContent = message;

      document.body.appendChild(notification);

      setTimeout(() => {
      notification.remove();
      }, 4000);
      }

      // Load conversations from server
      async function loadConversations() {
      const loadingEl = document.getElementById('loadingConversations');
      const listEl = document.getElementById('conversationList');

      try {
      loadingEl.classList.remove('hidden');

      const response = await fetch(`${BASE}index.php?p=get_conversations`);

      if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const text = await response.text();
      console.log('Raw response:', text); // Debug log

      let data;
      try {
      data = JSON.parse(text);
      } catch (e) {
      console.error('JSON parse error:', e);
      console.error('Response text:', text);
      throw new Error('Invalid JSON response from server');
      }

      if (data.success) {
      displayConversations(data.conversations);
      document.getElementById('conversationCount').textContent = data.conversations.length;
      } else {
      throw new Error(data.error || 'Failed to load conversations');
      }
      } catch (error) {
      console.error('Load conversations error:', error);
      listEl.innerHTML = `
      <div class="p-6 text-center text-gray-500">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
        </div>
        <p class="font-medium mb-2">Failed to load conversations</p>
        <p class="text-sm mb-4">${error.message}</p>
        <button onclick="loadConversations()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
          <i class="fas fa-redo mr-2"></i>Retry
        </button>
      </div>
      `;
      } finally {
      loadingEl.classList.add('hidden');
      }
      }

      // Display conversations in the sidebar with enhanced UI and unread counts
      function displayConversations(conversations) {
      const listEl = document.getElementById('conversationList');

      if (conversations.length === 0) {
      listEl.innerHTML = `
      <div class="p-8 text-center text-gray-500">
        <div class="w-20 h-20 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm">
          <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
          </svg>
        </div>
        <p class="font-semibold mb-2 text-gray-700">No conversations yet</p>
        <p class="text-sm text-gray-500">Start messaging by browsing listings</p>
      </div>
      `;
      return;
      }

      listEl.innerHTML = conversations.map(conv => {
      const isActive = currentConversation == conv.id;
      const otherUser = conv.other_user_name;
      const otherUserPic = conv.other_user_profile_pic;
      const unreadCount = conv.unread_count || 0;

      return `
      <div class="p-5 cursor-pointer transition-all duration-300 border-l-4 ${
        isActive 
          ? 'bg-blue-50 border-blue-500 shadow-md' 
          : 'bg-white border-transparent hover:border-blue-200 hover:shadow-sm hover:bg-gray-50'
      }"
        data-conversation-id="${conv.id}"
        onclick="openConversation(${conv.id}, '${otherUser}', '${otherUserPic || ''}', ${conv.listing_id || 'null'})">
        <div class="flex items-center space-x-4">
          <div class="relative">
            ${getProfilePicHtml(otherUserPic, otherUser, 'w-14 h-14')}
            ${unreadCount > 0 ? `<div class="absolute -top-1 -right-1 w-6 h-6 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center shadow-lg">${unreadCount > 99 ? '99+' : unreadCount}</div>` : ''}
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between mb-1">
              <h3 class="font-bold text-gray-900 truncate text-base">${otherUser}</h3>
              <span class="text-xs text-gray-500 font-medium">${formatTimeAgo(conv.last_message_at)}</span>
            </div>
            <p class="text-sm ${unreadCount > 0 ? 'text-gray-900 font-semibold' : 'text-gray-600'} truncate">
              ${conv.last_message ? (conv.last_message.includes('[IMAGES]') ? '📷 Image' : conv.last_message) : 'No messages yet'}
            </p>
            ${conv.listing_title ? `<p class="text-xs text-blue-600 truncate mt-2 bg-blue-50 px-2 py-1 rounded-lg inline-block">📋 ${conv.listing_title}</p>` : ''}
          </div>
        </div>
      </div>
      `;
      }).join('');
      }

      // Load messages for a conversation
      async function loadMessages(conversationId) {
      const messagesEl = document.getElementById('chatMessages');

      try {
      const response = await fetch(`${BASE}index.php?p=get_messages&conversation_id=${conversationId}`);
      if (!response.ok) throw new Error('Failed to load messages');

      const data = await response.json();

      if (data.success) {
      displayMessages(data.messages);
      } else {
      throw new Error(data.error || 'Failed to load messages');
      }
      } catch (error) {
      console.error('Load messages error:', error);
      messagesEl.innerHTML = `
      <div class="flex items-center justify-center h-full">
        <div class="text-center text-gray-500">
          <p>Failed to load messages</p>
          <button onclick="loadMessages(${conversationId})" class="mt-2 text-blue-600 hover:text-blue-800">Retry</button>
        </div>
      </div>
      `;
      }
      }

      // Display messages in chat area
      function displayMessages(messages) {
      const messagesEl = document.getElementById('chatMessages');

      if (messages.length === 0) {
      messagesEl.innerHTML = `
      <div class="flex items-center justify-center h-full">
        <div class="text-center text-gray-500">
          <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>
          </div>
          <p>No messages yet</p>
          <p class="text-sm mt-1">Start the conversation!</p>
        </div>
      </div>
      `;
      return;
      }

      messagesEl.innerHTML = messages.map(msg => {
      const isOwn = msg.sender_id == userId;
      return createMessageElement(msg, isOwn);
      }).join('');

      // Scroll to bottom
      messagesEl.scrollTop = messagesEl.scrollHeight;
      }

      // Generate enhanced profile picture HTML with actual image support
      function getProfilePicHtml(profilePic, name, sizeClass = 'w-8 h-8') {
      const initials = name ? name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) : '?';

      // Check if profile picture exists and is valid
      if (profilePic && profilePic.trim() !== '' && profilePic !== 'null' && profilePic !== 'undefined') {
      // Handle both relative and absolute URLs
      const imageUrl = profilePic.startsWith('http') ? profilePic : `${BASE}${profilePic}`;

      return `
      <div class="${sizeClass} rounded-full overflow-hidden flex-shrink-0 shadow-lg ring-2 ring-white bg-gradient-to-br from-blue-500 via-purple-500 to-indigo-600 relative">
        <img src="${imageUrl}"
          alt="${name || 'User'}"
          class="w-full h-full object-cover absolute inset-0"
          onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
          onload="this.nextElementSibling.style.display='none';">
        <div class="w-full h-full flex items-center justify-center absolute inset-0">
          <span class="text-white font-bold text-sm">${initials}</span>
        </div>
      </div>
      `;
      }

      // Fallback to initials when no profile picture
      return `
      <div class="${sizeClass} bg-gradient-to-br from-blue-500 via-purple-500 to-indigo-600 rounded-full flex items-center justify-center flex-shrink-0 shadow-lg ring-2 ring-white">
        <span class="text-white font-bold text-sm">${initials}</span>
      </div>
      `;
      }

      // Format time (e.g., "2:30 PM")
      function formatTime(dateString) {
      const date = new Date(dateString);
      return date.toLocaleTimeString('en-US', {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true
      });
      }

      // Format relative time (e.g., "2 hours ago")
      function formatTimeAgo(dateString) {
      const date = new Date(dateString);
      const now = new Date();
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMins / 60);
      const diffDays = Math.floor(diffHours / 24);

      if (diffMins < 1) return 'Just now' ;
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;

        return formatDate(dateString);
        }

        // Format date (e.g., "Jan 15" )
        function formatDate(dateString) {
        const date=new Date(dateString);
        return date.toLocaleDateString('en-US', {
        month: 'short' ,
        day: 'numeric'
        });
        }

        // Auto refresh every 10 seconds
        setInterval(()=> {
        if (currentConversation) {
        loadMessages(currentConversation);
        // Don't refresh conversations list while user is actively chatting
        // to prevent unread badges from reappearing
        } else {
        // Only refresh conversations list when no conversation is active
        loadConversations();
        }
        }, 10000);

        // Function to update parent window sidebar (if in dashboard)
        function updateParentSidebar() {
        try {
        // Try to call parent window function if it exists
        if (window.parent && window.parent.updateSidebarMessageBadge) {
        window.parent.updateSidebarMessageBadge();
        }
        // Also try direct function call if on same page
        if (typeof updateSidebarMessageBadge === 'function') {
        updateSidebarMessageBadge();
        }
        } catch (error) {
        console.log('Could not update parent sidebar:', error);
        }
        }

        // Image modal functions (keep existing)
        function openImageModal(imageSrc) {
        document.getElementById('modalImage').src = imageSrc;
        document.getElementById('imageModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
        document.getElementById('imageModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
        }

        // Close modal on click outside or Escape key
        document.getElementById('imageModal').addEventListener('click', function(e) {
        if (e.target === this) closeImageModal();
        });

        document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeImageModal();
        });

        // Message page sidebar toggle functionality
        function toggleMessageSidebar() {
        // Dispatch the same event that the navbar toggle uses
        const toggleEvent = new CustomEvent('toggleSidebar', {
        detail: {
        source: 'message-page'
        }
        });
        window.dispatchEvent(toggleEvent);
        console.log('🔘 Message page sidebar toggle clicked');
        }

        // Hide the message page toggle button when sidebar is open on mobile
        function updateMessageToggleVisibility() {
        const messageToggle = document.getElementById('messageSidebarToggle');
        const sidebar = document.getElementById('sidebar');

        if (messageToggle && sidebar && window.innerWidth < 1024) {
          if (sidebar.classList.contains('show')) {
          messageToggle.style.display='none' ;
          } else {
          messageToggle.style.display='flex' ;
          }
          }
          }

          // Listen for sidebar state changes
          window.addEventListener('sidebarStateChanged', updateMessageToggleVisibility);

          // Check sidebar state on window resize
          window.addEventListener('resize', ()=> {
          const messageToggle = document.getElementById('messageSidebarToggle');
          if (window.innerWidth >= 1024 && messageToggle) {
          messageToggle.style.display = 'none';
          } else if (window.innerWidth < 1024 && messageToggle) {
            updateMessageToggleVisibility();
            }
            });

            // Initialize toggle button visibility
            document.addEventListener('DOMContentLoaded', function() {
            updateMessageToggleVisibility();
            });
            </script>