export const getToken = () => {
	return uni.getStorageSync('token') || '';
};

export const setToken = (token) => {
	uni.setStorageSync('token', token);
};

export const removeToken = () => {
	uni.removeStorageSync('token');
};

export const getUserId = () => {
	return uni.getStorageSync('id') || '';
};

export const getUserType = () => {
	return uni.getStorageSync('type') || '';
};