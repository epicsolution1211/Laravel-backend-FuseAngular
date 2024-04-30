// Give the service worker access to Firebase Messaging.
// Note that you can only use Firebase Messaging here. Other Firebase libraries
// are not available in the service worker.importScripts('https://www.gstatic.com/firebasejs/7.23.0/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');
/*
Initialize the Firebase app in the service worker by passing in the messagingSenderId.
*/
firebase.initializeApp({
    apiKey: 'AIzaSyAX0aFL7HBcVOel9HYOe4EdQCRO5TWOU74',
    authDomain: 'atavism-ff2dd.firebaseapp.com',
    databaseURL: 'https://atavism-ff2dd.firebaseapp.com',
    projectId: 'atavism-ff2dd',
    storageBucket: 'atavism-ff2dd.appspot.com',
    messagingSenderId: '141085688390',
    appId: '1:141085688390:web:d022c87aa311382f4f8047',
    measurementId: 'G-VZSE2N6CKM',
});

// Retrieve an instance of Firebase Messaging so that it can handle background
// messages.
const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function (payload) {
    console.log("Message received.", payload);
    const title = "Hello world is awesome";
    const options = {
        body: "Your notificaiton message .",
        icon: "/firebase-logo.png",
    };
    return self.registration.showNotification(
        title,
        options,
    );
});