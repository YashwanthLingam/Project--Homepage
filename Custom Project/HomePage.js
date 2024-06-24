import { useState, useEffect } from 'react';
import Cookies from 'js-cookie';

const Home = () => {
  const [user, setUser] = useState(null);

  useEffect(() => {
    const fetchUserInfo = async () => {
      const token = Cookies.get('token');

      if (!token) {
        Router.push('/login'); // Redirect to login if token not found
        return;
      }

      try {
        const response = await fetch('/wp-json/custom-auth-plugin/v1/user-info', {
          headers: {
            Authorization: token,
          },
        });

        if (!response.ok) {
          throw new Error('Invalid token or user not found.');
        }

        const data = await response.json();
        setUser(data);
      } catch (error) {
        console.error('Error fetching user info:', error);
        Router.push('/login'); // Redirect to login page on error
      }
    };

    fetchUserInfo();
  }, []);

  return (
    <div>
      {user ? (
        <p>Welcome, {user.username} ({user.email})</p>
      ) : (
        <p>Loading...</p>
      )}
    </div>
  );
};

export default Home;
