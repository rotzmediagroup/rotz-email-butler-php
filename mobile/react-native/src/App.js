/**
 * ROTZ Email Butler - Mobile Application
 * Main App Component
 */

import React, { useEffect, useState } from 'react';
import {
  SafeAreaView,
  StatusBar,
  StyleSheet,
  useColorScheme,
  Alert,
  AppState,
  Platform,
} from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createStackNavigator } from '@react-navigation/stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import AsyncStorage from '@react-native-async-storage/async-storage';
import PushNotification from 'react-native-push-notification';
import BackgroundJob from 'react-native-background-job';
import DeviceInfo from 'react-native-device-info';
import NetInfo from '@react-native-community/netinfo';
import Icon from 'react-native-vector-icons/MaterialIcons';

// Screens
import LoginScreen from './screens/LoginScreen';
import DashboardScreen from './screens/DashboardScreen';
import EmailsScreen from './screens/EmailsScreen';
import AIProvidersScreen from './screens/AIProvidersScreen';
import EmailAccountsScreen from './screens/EmailAccountsScreen';
import SettingsScreen from './screens/SettingsScreen';
import AnalyticsScreen from './screens/AnalyticsScreen';
import ProfileScreen from './screens/ProfileScreen';
import NotificationsScreen from './screens/NotificationsScreen';

// Services
import AuthService from './services/AuthService';
import EmailService from './services/EmailService';
import AIService from './services/AIService';
import NotificationService from './services/NotificationService';
import SyncService from './services/SyncService';

// Utils
import { Colors } from './utils/Colors';
import { Fonts } from './utils/Fonts';
import { API_BASE_URL } from './utils/Constants';

const Stack = createStackNavigator();
const Tab = createBottomTabNavigator();

// Main Tab Navigator
function MainTabNavigator() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        tabBarIcon: ({ focused, color, size }) => {
          let iconName;

          switch (route.name) {
            case 'Dashboard':
              iconName = 'dashboard';
              break;
            case 'Emails':
              iconName = 'email';
              break;
            case 'AI Providers':
              iconName = 'psychology';
              break;
            case 'Accounts':
              iconName = 'account-circle';
              break;
            case 'Analytics':
              iconName = 'analytics';
              break;
            case 'Settings':
              iconName = 'settings';
              break;
            default:
              iconName = 'help';
          }

          return <Icon name={iconName} size={size} color={color} />;
        },
        tabBarActiveTintColor: Colors.primary,
        tabBarInactiveTintColor: Colors.gray,
        tabBarStyle: {
          backgroundColor: Colors.white,
          borderTopColor: Colors.lightGray,
          paddingBottom: Platform.OS === 'ios' ? 20 : 5,
          height: Platform.OS === 'ios' ? 85 : 60,
        },
        headerStyle: {
          backgroundColor: Colors.primary,
        },
        headerTintColor: Colors.white,
        headerTitleStyle: {
          fontFamily: Fonts.bold,
          fontSize: 18,
        },
      })}
    >
      <Tab.Screen 
        name="Dashboard" 
        component={DashboardScreen}
        options={{
          title: 'Dashboard',
          headerTitle: 'ROTZ Email Butler',
        }}
      />
      <Tab.Screen 
        name="Emails" 
        component={EmailsScreen}
        options={{
          title: 'Emails',
          tabBarBadge: null, // Will be updated with unread count
        }}
      />
      <Tab.Screen 
        name="AI Providers" 
        component={AIProvidersScreen}
        options={{
          title: 'AI',
        }}
      />
      <Tab.Screen 
        name="Accounts" 
        component={EmailAccountsScreen}
        options={{
          title: 'Accounts',
        }}
      />
      <Tab.Screen 
        name="Analytics" 
        component={AnalyticsScreen}
        options={{
          title: 'Analytics',
        }}
      />
      <Tab.Screen 
        name="Settings" 
        component={SettingsScreen}
        options={{
          title: 'Settings',
        }}
      />
    </Tab.Navigator>
  );
}

// Main App Component
function App() {
  const isDarkMode = useColorScheme() === 'dark';
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [appState, setAppState] = useState(AppState.currentState);
  const [isConnected, setIsConnected] = useState(true);

  useEffect(() => {
    initializeApp();
    setupAppStateListener();
    setupNetworkListener();
    setupPushNotifications();
    setupBackgroundSync();
    
    return () => {
      BackgroundJob.stop();
    };
  }, []);

  const initializeApp = async () => {
    try {
      // Check authentication status
      const token = await AsyncStorage.getItem('auth_token');
      if (token) {
        const isValid = await AuthService.validateToken(token);
        setIsAuthenticated(isValid);
      }

      // Initialize services
      await NotificationService.initialize();
      await SyncService.initialize();

      // Get device info for analytics
      const deviceInfo = {
        deviceId: await DeviceInfo.getUniqueId(),
        deviceName: await DeviceInfo.getDeviceName(),
        systemVersion: DeviceInfo.getSystemVersion(),
        appVersion: DeviceInfo.getVersion(),
        buildNumber: DeviceInfo.getBuildNumber(),
      };

      await AsyncStorage.setItem('device_info', JSON.stringify(deviceInfo));

    } catch (error) {
      console.error('App initialization error:', error);
      Alert.alert('Error', 'Failed to initialize app. Please restart.');
    } finally {
      setIsLoading(false);
    }
  };

  const setupAppStateListener = () => {
    const handleAppStateChange = (nextAppState) => {
      if (appState.match(/inactive|background/) && nextAppState === 'active') {
        // App has come to the foreground
        if (isAuthenticated) {
          SyncService.syncEmails();
        }
      }
      setAppState(nextAppState);
    };

    const subscription = AppState.addEventListener('change', handleAppStateChange);
    return () => subscription?.remove();
  };

  const setupNetworkListener = () => {
    const unsubscribe = NetInfo.addEventListener(state => {
      setIsConnected(state.isConnected);
      
      if (state.isConnected && isAuthenticated) {
        // Reconnected - sync emails
        SyncService.syncEmails();
      }
    });

    return unsubscribe;
  };

  const setupPushNotifications = () => {
    PushNotification.configure({
      onRegister: function(token) {
        console.log('Push notification token:', token);
        // Send token to server
        if (isAuthenticated) {
          AuthService.updatePushToken(token.token);
        }
      },

      onNotification: function(notification) {
        console.log('Push notification received:', notification);
        
        if (notification.userInteraction) {
          // User tapped notification
          handleNotificationTap(notification);
        }
      },

      onAction: function(notification) {
        console.log('Push notification action:', notification.action);
      },

      onRegistrationError: function(err) {
        console.error('Push notification registration error:', err);
      },

      permissions: {
        alert: true,
        badge: true,
        sound: true,
      },

      popInitialNotification: true,
      requestPermissions: Platform.OS === 'ios',
    });
  };

  const setupBackgroundSync = () => {
    if (Platform.OS === 'android') {
      BackgroundJob.register({
        jobKey: 'emailSync',
        period: 15000, // 15 minutes
      });

      BackgroundJob.on('emailSync', async () => {
        if (isAuthenticated && isConnected) {
          try {
            await SyncService.backgroundSync();
          } catch (error) {
            console.error('Background sync error:', error);
          }
        }
      });

      BackgroundJob.start({
        jobKey: 'emailSync',
      });
    }
  };

  const handleNotificationTap = (notification) => {
    // Navigate to appropriate screen based on notification type
    switch (notification.data?.type) {
      case 'new_email':
        // Navigate to emails screen
        break;
      case 'ai_processing_complete':
        // Navigate to dashboard
        break;
      case 'sync_error':
        // Navigate to accounts screen
        break;
      default:
        // Navigate to dashboard
        break;
    }
  };

  const handleLogin = async (credentials) => {
    try {
      const result = await AuthService.login(credentials);
      if (result.success) {
        setIsAuthenticated(true);
        await SyncService.initialize();
        return { success: true };
      } else {
        return { success: false, error: result.error };
      }
    } catch (error) {
      return { success: false, error: 'Login failed. Please try again.' };
    }
  };

  const handleLogout = async () => {
    try {
      await AuthService.logout();
      setIsAuthenticated(false);
      BackgroundJob.stop();
    } catch (error) {
      console.error('Logout error:', error);
    }
  };

  if (isLoading) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <StatusBar
          barStyle={isDarkMode ? 'light-content' : 'dark-content'}
          backgroundColor={Colors.primary}
        />
        {/* Add loading spinner here */}
      </SafeAreaView>
    );
  }

  return (
    <NavigationContainer>
      <StatusBar
        barStyle={isDarkMode ? 'light-content' : 'dark-content'}
        backgroundColor={Colors.primary}
      />
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {isAuthenticated ? (
          <>
            <Stack.Screen name="Main" component={MainTabNavigator} />
            <Stack.Screen 
              name="Profile" 
              component={ProfileScreen}
              options={{
                headerShown: true,
                title: 'Profile',
                headerStyle: { backgroundColor: Colors.primary },
                headerTintColor: Colors.white,
              }}
            />
            <Stack.Screen 
              name="Notifications" 
              component={NotificationsScreen}
              options={{
                headerShown: true,
                title: 'Notifications',
                headerStyle: { backgroundColor: Colors.primary },
                headerTintColor: Colors.white,
              }}
            />
          </>
        ) : (
          <Stack.Screen name="Login">
            {props => <LoginScreen {...props} onLogin={handleLogin} />}
          </Stack.Screen>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}

const styles = StyleSheet.create({
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: Colors.white,
  },
});

export default App;

