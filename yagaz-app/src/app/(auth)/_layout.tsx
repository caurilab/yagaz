import { Stack } from 'expo-router';

export default function LayoutAuth() {
  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="connexion" />
      <Stack.Screen name="inscription" />
    </Stack>
  );
}
